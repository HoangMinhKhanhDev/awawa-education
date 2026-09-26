import { registerSW } from 'virtual:pwa-register';

// Không đăng ký service worker ở môi trường local để tránh cache bản cũ khi phát triển.
const isLocalhost = ['localhost', '127.0.0.1', '::1', ''].includes(window.location.hostname);

if (! isLocalhost && 'serviceWorker' in navigator) {
    registerSW({ immediate: true });
}

const storedTheme = () => {
    try {
        return localStorage.getItem('awawa-theme');
    } catch (error) {
        return null;
    }
};

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);

    return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function jsonHeaders() {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
    };
}

window.awawa = {
    theme() {
        return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
    },
    setTheme(theme) {
        document.documentElement.classList.toggle('dark', theme === 'dark');

        try {
            localStorage.setItem('awawa-theme', theme);
        } catch (error) {
            // bỏ qua khi trình duyệt chặn localStorage
        }
    },
    toggleTheme() {
        const next = this.theme() === 'dark' ? 'light' : 'dark';
        this.setTheme(next);

        return next;
    },
    storedTheme,
};

window.AwawaPush = {
    supported() {
        return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    },

    publicKey() {
        return document.querySelector('meta[name="vapid-public-key"]')?.content || null;
    },

    async enable() {
        if (!this.supported()) {
            return { ok: false, reason: 'unsupported' };
        }

        const key = this.publicKey();

        if (!key) {
            return { ok: false, reason: 'not-configured' };
        }

        const permission = await Notification.requestPermission();

        if (permission !== 'granted') {
            return { ok: false, reason: 'denied' };
        }

        const registration = await navigator.serviceWorker.ready;
        let subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(key),
            });
        }

        const response = await fetch('/push/subscribe', {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify(subscription.toJSON()),
        });

        return { ok: response.ok };
    },

    async disable() {
        if (!this.supported()) {
            return { ok: false, reason: 'unsupported' };
        }

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
            return { ok: true };
        }

        const endpoint = subscription.endpoint;
        await subscription.unsubscribe();

        const response = await fetch('/push/unsubscribe', {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify({ endpoint }),
        });

        return { ok: response.ok };
    },

    async status() {
        if (!this.supported()) {
            return 'unsupported';
        }

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();

        return subscription ? 'enabled' : 'disabled';
    },
};
