import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const devUserId = new URLSearchParams(location.search).get('as');

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    // 開発用: ?as={userId} のタブでは、そのユーザーとしてチャンネル認証する
    auth: { headers: devUserId ? { 'X-Dev-User': devUserId } : {} },
    enabledTransports: ['ws', 'wss'],
});
