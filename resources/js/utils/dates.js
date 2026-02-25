/**
 * Safely parse a date string that may come as "YYYY-MM-DD"
 * or as a full ISO string "YYYY-MM-DDTHH:mm:ss.SSSSSSZ".
 * Using T12:00:00 (noon) avoids timezone rollback to the previous day.
 */
function parseDate(dateString) {
    if (!dateString) return null;
    const datePart = String(dateString).split('T')[0]; // "2026-09-06"
    return new Date(datePart + 'T12:00:00');
}

export function formatDate(dateString) {
    const date = parseDate(dateString);
    if (!date) return '';
    return date.toLocaleDateString('es-ES', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

export function formatDateShort(dateString) {
    const date = parseDate(dateString);
    if (!date) return '';
    return date.toLocaleDateString('es-ES', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export function formatDateMedium(dateString) {
    const date = parseDate(dateString);
    if (!date) return '';
    return date.toLocaleDateString('es-ES', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
}

export function formatDateRange(startDate, endDate) {
    return `${formatDateShort(startDate)} – ${formatDateShort(endDate)}`;
}

export function formatDayLabel(dateString) {
    const date = parseDate(dateString);
    if (!date) return '—';
    return date.toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    });
}

/**
 * Format a time string "HH:mm" or "HH:mm:ss" → "11:00 AM"
 */
export function formatTime(timeString) {
    if (!timeString) return '';
    const [hours, minutes] = String(timeString).split(':');
    const hour = parseInt(hours, 10);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
}
