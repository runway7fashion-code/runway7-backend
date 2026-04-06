<script setup>
import { ref, onMounted, watch } from 'vue';

const props = defineProps({
    latitude: { type: [Number, String], default: null },
    longitude: { type: [Number, String], default: null },
    address: { type: String, default: '' },
    venueName: { type: String, default: '' },
});

const emit = defineEmits(['update:latitude', 'update:longitude', 'update:address', 'update:venueName']);

const mapContainer = ref(null);
const searchInput = ref(null);
const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

let map = null;
let marker = null;
let geocoder = null;
let loaded = false;

function loadGoogleMaps() {
    return new Promise((resolve, reject) => {
        if (window.google?.maps) {
            resolve();
            return;
        }

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places`;
        script.async = true;
        script.defer = true;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

function initMap() {
    const lat = parseFloat(props.latitude) || 40.7580;
    const lng = parseFloat(props.longitude) || -73.9855;
    const hasCoords = props.latitude && props.longitude;

    map = new google.maps.Map(mapContainer.value, {
        center: { lat, lng },
        zoom: hasCoords ? 16 : 12,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false,
    });

    marker = new google.maps.Marker({
        map,
        draggable: true,
        visible: hasCoords,
        position: { lat, lng },
    });

    geocoder = new google.maps.Geocoder();

    // Drag marker to update coords
    marker.addListener('dragend', () => {
        const pos = marker.getPosition();
        emit('update:latitude', pos.lat());
        emit('update:longitude', pos.lng());

        // Reverse geocode to get address
        geocoder.geocode({ location: pos }, (results, status) => {
            if (status === 'OK' && results[0]) {
                emit('update:address', results[0].formatted_address);
            }
        });
    });

    // Click on map to place marker
    map.addListener('click', (e) => {
        const pos = e.latLng;
        marker.setPosition(pos);
        marker.setVisible(true);
        emit('update:latitude', pos.lat());
        emit('update:longitude', pos.lng());

        geocoder.geocode({ location: pos }, (results, status) => {
            if (status === 'OK' && results[0]) {
                emit('update:address', results[0].formatted_address);
            }
        });
    });

    // Places autocomplete
    const autocomplete = new google.maps.places.Autocomplete(searchInput.value, {
        types: ['establishment', 'geocode'],
    });

    autocomplete.bindTo('bounds', map);

    autocomplete.addListener('place_changed', () => {
        const place = autocomplete.getPlace();

        if (!place.geometry?.location) return;

        const pos = place.geometry.location;

        map.setCenter(pos);
        map.setZoom(17);
        marker.setPosition(pos);
        marker.setVisible(true);

        emit('update:latitude', pos.lat());
        emit('update:longitude', pos.lng());
        emit('update:address', place.formatted_address || '');

        if (place.name && place.name !== place.formatted_address) {
            emit('update:venueName', place.name);
        }
    });
}

onMounted(async () => {
    try {
        await loadGoogleMaps();
        initMap();
        loaded = true;
    } catch (e) {
        console.error('Failed to load Google Maps:', e);
    }
});

// Watch for external changes to coords
watch(() => [props.latitude, props.longitude], ([lat, lng]) => {
    if (!loaded || !marker) return;
    const newLat = parseFloat(lat);
    const newLng = parseFloat(lng);
    if (!isNaN(newLat) && !isNaN(newLng)) {
        const pos = new google.maps.LatLng(newLat, newLng);
        marker.setPosition(pos);
        marker.setVisible(true);
        map.setCenter(pos);
    }
});
</script>

<template>
    <div class="space-y-3">
        <!-- Search input -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Search Venue</label>
            <input
                ref="searchInput"
                type="text"
                placeholder="Search by name or address..."
                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10"
            />
        </div>

        <!-- Map -->
        <div ref="mapContainer" class="w-full h-64 rounded-xl border border-gray-200 bg-gray-100"></div>

        <!-- Coords display -->
        <div v-if="latitude && longitude" class="flex items-center gap-3 text-xs text-gray-400">
            <span>Lat: {{ parseFloat(latitude).toFixed(7) }}</span>
            <span>Lng: {{ parseFloat(longitude).toFixed(7) }}</span>
            <span v-if="address" class="truncate">{{ address }}</span>
        </div>
    </div>
</template>
