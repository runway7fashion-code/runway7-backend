import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echoInstance = null;

/**
 * Initialize Echo using Reverb config shared from the server via Inertia.
 * Call once from a component with access to `page.props.reverb`.
 */
export function initEcho(config) {
    if (echoInstance) return echoInstance;
    if (!config?.key || !config?.host) return null;

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        // Use axios for auth — it already handles session cookie + XSRF token
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                window.axios.post('/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name,
                })
                .then(response => callback(null, response.data))
                .catch(error => {
                    console.error('[Echo] auth failed for', channel.name, error?.response?.status, error?.response?.data);
                    callback(error);
                });
            },
        }),
    });

    return echoInstance;
}

export function getEcho() {
    return echoInstance;
}
