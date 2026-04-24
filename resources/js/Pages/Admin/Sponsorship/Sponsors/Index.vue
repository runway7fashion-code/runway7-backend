<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { EyeIcon, EnvelopeIcon, MagnifyingGlassIcon, CheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    sponsors: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
let to;
function onSearch() {
    clearTimeout(to);
    to = setTimeout(() => {
        router.get('/admin/sponsorship/sponsors', { search: search.value }, { preserveState: true, replace: true });
    }, 350);
}

function sendOnboarding(sponsor) {
    if (!confirm(`Send onboarding email to ${sponsor.first_name} ${sponsor.last_name}?`)) return;
    useForm({}).post(`/admin/sponsorship/sponsors/${sponsor.id}/send-onboarding`, { preserveScroll: true });
}

function formatDate(d) {
    if (!d) return null;
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">Sponsors</h2>
        </template>

        <div class="max-w-7xl mx-auto space-y-4">
            <div class="relative">
                <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" />
                <input v-model="search" @input="onSearch" type="text" placeholder="Search sponsor by name, email or company..."
                    class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10" />
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Sponsor</th>
                            <th class="px-4 py-3 font-medium">Company</th>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Contracts</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Onboarded</th>
                            <th class="px-4 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="s in sponsors.data" :key="s.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ s.first_name }} {{ s.last_name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ s.sponsor_profile?.company_name || '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 text-xs">{{ s.email }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ s.registrations_count }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize"
                                    :class="{
                                        'bg-green-100 text-green-700': s.status === 'active',
                                        'bg-yellow-100 text-yellow-700': s.status === 'pending' || s.status === 'registered',
                                        'bg-gray-100 text-gray-500': s.status === 'inactive'
                                    }">
                                    {{ s.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">
                                <span v-if="s.welcome_email_sent_at" class="inline-flex items-center gap-1 text-green-600">
                                    <CheckIcon class="w-3 h-3" /> {{ formatDate(s.welcome_email_sent_at) }}
                                </span>
                                <span v-else class="text-gray-400">Not sent</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex gap-1">
                                    <button @click="sendOnboarding(s)"
                                        :title="s.welcome_email_sent_at ? 'Re-send onboarding' : 'Send onboarding'"
                                        class="p-1.5 rounded-lg hover:bg-yellow-50 text-gray-400 hover:text-[#D4AF37]">
                                        <EnvelopeIcon class="w-4 h-4" />
                                    </button>
                                    <Link :href="`/admin/sponsorship/sponsors/${s.id}`" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600">
                                        <EyeIcon class="w-4 h-4" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!sponsors.data.length">
                            <td colspan="7" class="px-6 py-12 text-center text-gray-400 text-sm">
                                No sponsors yet. Convert a lead to create the first one.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="sponsors.last_page > 1" class="flex justify-center gap-1">
                <Link v-for="link in sponsors.links" :key="link.label" :href="link.url ?? '#'"
                    class="px-3 py-1.5 text-sm rounded-lg border"
                    :class="[link.active ? 'bg-black text-white border-black' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50', !link.url ? 'opacity-40 pointer-events-none' : '']"
                    v-html="link.label"></Link>
            </div>
        </div>
    </AdminLayout>
</template>
