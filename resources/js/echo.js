import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const runtimeConfig = window.__CHAT_REVERB_CONFIG__ ?? {};
const reverbScheme = runtimeConfig.scheme ?? import.meta.env.VITE_REVERB_SCHEME ?? 'https';
const reverbPort = Number(runtimeConfig.port ?? import.meta.env.VITE_REVERB_PORT ?? (reverbScheme === 'https' ? 443 : 80));

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: runtimeConfig.key ?? import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: runtimeConfig.host ?? import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
});
