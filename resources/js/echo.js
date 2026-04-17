import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echoInstance = null;

/**
 * Initialize Echo using Reverb config shared from the server via Inertia.
 * Call once from a component with access to `page.props.reverb` (e.g. chat pages).
 */
export function initEcho(config) {
    if (echoInstance) return echoInstance;
    if (!config?.key || !config?.host) return null;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        || decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-XSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        },
    });

    return echoInstance;
}

export function getEcho() {
    return echoInstance;
}
