// Conditionally load Echo and Pusher only in the browser/runtime.
if (typeof window !== 'undefined' && !import.meta.env.SSR) {
    (async () => {
        const EchoModule = await import('laravel-echo').catch(() => null);
        if (!EchoModule) return;
        const Echo = EchoModule.default ?? EchoModule;

        const PusherModule = await import('pusher-js').catch(() => null);
        const Pusher = PusherModule?.default ?? PusherModule;
        if (Pusher) window.Pusher = Pusher;

        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    })();
}
