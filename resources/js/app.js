import { registerSW } from 'virtual:pwa-register';

// Chỉ đăng ký service worker ở bản PROD đã build: ở dev không có file sw.js
// (gây 404) và cache cũ còn phục vụ asset cũ đè lên blade mới.
const isLocalhost = ['localhost', '127.0.0.1', '::1', ''].includes(window.location.hostname);

if (import.meta.env.PROD && ! isLocalhost && 'serviceWorker' in navigator) {
    registerSW({ immediate: true });
}

const THEME_STORAGE_KEY = 'awawa-theme';
const THEME_PREFERENCES = ['system', 'light', 'dark'];
const THEME_LABELS = {
    system: 'Giao diện: theo hệ thống. Bấm để chuyển sang sáng',
    light: 'Giao diện: sáng. Bấm để chuyển sang tối',
    dark: 'Giao diện: tối. Bấm để chuyển sang theo hệ thống',
};
const THEME_META_LIGHT = document.querySelector('meta[name="theme-color"]')?.content ?? null;

function themeMetaColor(resolved) {
    if (THEME_META_LIGHT === null) {
        return null;
    }

    // Nền tối lấy đúng --color-night-900 trong app.css.
    return resolved === 'dark' ? '#0e1118' : THEME_META_LIGHT;
}

const darkQuery = window.matchMedia('(prefers-color-scheme: dark)');

const storedTheme = () => {
    try {
        return localStorage.getItem(THEME_STORAGE_KEY);
    } catch (error) {
        return null;
    }
};

const systemTheme = () => (darkQuery.matches ? 'dark' : 'light');

const normalizePreference = (value) =>
    THEME_PREFERENCES.includes(value) ? value : 'system';

function applyTheme(preference) {
    const normalized = normalizePreference(preference);
    const resolved = normalized === 'system' ? systemTheme() : normalized;
    const root = document.documentElement;

    root.classList.toggle('dark', resolved === 'dark');
    root.dataset.theme = normalized;
    root.style.colorScheme = resolved;

    const metaColor = themeMetaColor(resolved);
    if (metaColor !== null) {
        document
            .querySelector('meta[name="theme-color"]')
            ?.setAttribute('content', metaColor);
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-label', THEME_LABELS[normalized]);
        button.setAttribute('aria-pressed', String(normalized === 'dark'));
        button.setAttribute('title', THEME_LABELS[normalized]);
    });

    document.dispatchEvent(
        new CustomEvent('awawa:theme', { detail: { preference: normalized, resolved } }),
    );

    return resolved;
}

// Khi hệ thống đổi nền sáng/tối mà người dùng chưa chọn tay thì theo hệ thống;
// đã chọn tay thì giữ nguyên lựa chọn, không nhảy.
darkQuery.addEventListener('change', () => {
    if (normalizePreference(storedTheme()) === 'system') {
        applyTheme('system');
    }
});

// Khẳng định lại theme đúng sau navigate và khi quay lại tab (bfcache restore
// không chạy lại script). Hàm này hội tụ về cùng một giá trị nên gọi thừa
// cũng không gây nháy thêm — ngược lại nó sửa mọi lệch pha nếu có.
function reassertTheme() {
    applyTheme(normalizePreference(storedTheme()));
}

document.addEventListener('livewire:navigated', reassertTheme);
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        reassertTheme();
    }
});

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
    /** Lựa chọn đang lưu: 'system' | 'light' | 'dark'. */
    preference() {
        return normalizePreference(storedTheme());
    },
    /** Theme thực sự đang áp dụng sau khi đã phân giải 'system'. */
    resolvedTheme() {
        return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
    },
    setPreference(preference) {
        const normalized = normalizePreference(preference);

        try {
            localStorage.setItem(THEME_STORAGE_KEY, normalized);
        } catch (error) {
            // bị chặn localStorage: theme chỉ giữ trong phiên này
        }

        applyTheme(normalized);

        return normalized;
    },
    /** Vòng lặp system -> light -> dark -> system. */
    cyclePreference() {
        const current = this.preference();
        const next = THEME_PREFERENCES[(THEME_PREFERENCES.indexOf(current) + 1) % THEME_PREFERENCES.length];

        return this.setPreference(next);
    },
    storedTheme,
    applyTheme,
};

// Head script đã đặt class và data-theme; gọi lại một lần để nút toggle lấy
// aria-label đúng và các bundle khác (whiteboard) nhận được theme hiện tại.
applyTheme(window.awawa.preference());

/*
 * Phản hồi thị giác khi chuyển trang bằng wire:navigate.
 *
 * Thanh tiến trình 2px mặc định của Livewire rất khó thấy trên điện thoại, nên
 * lúc chờ server người dùng tưởng ấn hụt rồi ấn lại. Ở đây làm mờ vùng nội
 * dung và làm nổi tab đang chuyển, nhưng cố ý KHÔNG khoá pointer trên thanh
 * điều hướng dưới để vẫn bấm sang tab khác được khi trang đang tải.
 */
const NAV_CONTENT_ID = 'app-content';
const NAV_PENDING_CLASS = 'nav-pending';

function navLinks() {
    return Array.from(document.querySelectorAll('a[wire\\:navigate][href]'));
}

function clearPendingLinks() {
    document.querySelectorAll(`.${NAV_PENDING_CLASS}`).forEach((link) => {
        link.classList.remove(NAV_PENDING_CLASS);
    });
}

document.addEventListener('livewire:navigating', (event) => {
    const destination = event.detail?.url ? String(event.detail.url) : null;
    const content = document.getElementById(NAV_CONTENT_ID);

    content?.setAttribute('data-navigating', 'true');
    clearPendingLinks();

    if (destination === null) {
        return;
    }

    navLinks().forEach((link) => {
        if (link.href === destination) {
            link.classList.add(NAV_PENDING_CLASS);
        }
    });
});

document.addEventListener('livewire:navigated', () => {
    document.getElementById(NAV_CONTENT_ID)?.removeAttribute('data-navigating');
    clearPendingLinks();
});

/*
 * Menu mobile (drawer) bằng JS thuần, không phụ thuộc Alpine.
 *
 * Drawer từng dùng x-data/x-show; khi Alpine khởi tạo lỗi (đã thấy
 * `drawer is not defined` trên production) thì menu chết hoàn toàn. Bản này
 * mặc định `hidden`, mở/đóng bằng class nên luôn hoạt động.
 */
function drawerParts() {
    const root = document.getElementById('mobile-drawer');

    if (! root) {
        return null;
    }

    return {
        root,
        backdrop: root.querySelector('[data-drawer-backdrop]'),
        panel: root.querySelector('[data-drawer-panel]'),
        toggles: Array.from(document.querySelectorAll('[aria-controls="mobile-drawer"]')),
    };
}

window.awawaDrawer = {
    open() {
        const parts = drawerParts();

        if (! parts || ! parts.root.classList.contains('hidden')) {
            return;
        }

        parts.root.classList.remove('hidden');
        parts.root.setAttribute('aria-hidden', 'false');
        parts.toggles.forEach((button) => button.setAttribute('aria-expanded', 'true'));
        document.body.classList.add('overflow-hidden');

        requestAnimationFrame(() => requestAnimationFrame(() => {
            parts.backdrop?.classList.remove('opacity-0');
            parts.panel?.classList.remove('-translate-x-full');
        }));
    },
    close() {
        const parts = drawerParts();

        if (! parts || parts.root.classList.contains('hidden')) {
            return;
        }

        parts.backdrop?.classList.add('opacity-0');
        parts.panel?.classList.add('-translate-x-full');
        parts.root.setAttribute('aria-hidden', 'true');
        parts.toggles.forEach((button) => button.setAttribute('aria-expanded', 'false'));
        document.body.classList.remove('overflow-hidden');

        setTimeout(() => {
            drawerParts()?.root.classList.add('hidden');
        }, 220);
    },
};

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        window.awawaDrawer.close();
    }
});

// Đóng menu khi chuyển trang xong, kẻo drawer treo sau wire:navigate.
document.addEventListener('livewire:navigated', () => {
    window.awawaDrawer.close();
});

/*
 * Thanh tiến độ chuyển trang bằng JS thuần (chạy cùng listener dim nội dung
 * ở trên). Không dùng Alpine để không thêm scope nào vào shell layout.
 */
let navProgressTimer = null;

function navProgressEls() {
    const bar = document.getElementById('nav-progress');
    const fill = document.getElementById('nav-progress-fill');

    return bar && fill ? { bar, fill } : null;
}

document.addEventListener('livewire:navigating', () => {
    const els = navProgressEls();

    if (! els) {
        return;
    }

    els.bar.classList.remove('hidden');

    let width = 8;

    els.fill.style.width = '8%';
    clearInterval(navProgressTimer);
    navProgressTimer = setInterval(() => {
        width = Math.min(90, width + (90 - width) * 0.12);
        els.fill.style.width = `${width}%`;
    }, 120);
});

document.addEventListener('livewire:navigated', () => {
    const els = navProgressEls();

    if (! els) {
        return;
    }

    clearInterval(navProgressTimer);
    els.fill.style.width = '100%';

    setTimeout(() => {
        els.bar.classList.add('hidden');
        els.fill.style.width = '0%';
    }, 200);
});

window.awawaNotebookPanels = () => ({
    dragging: null,
    sourcesWidth: 300,
    studioWidth: 380,
    limits: { sources: [240, 560], studio: [280, 900], chat: 380 },
    storageKey: 'awawa.notebook.panels.v1',

    get isDesktop() {
        return window.matchMedia('(min-width: 1024px)').matches;
    },

    init() {
        this.restore();
        this.clamp();

        if (! window.__awawaNotebookPanelsBound) {
            window.__awawaNotebookPanelsBound = true;
            window.addEventListener('resize', () => this.clamp());
        }
    },

    restore() {
        try {
            const saved = JSON.parse(window.localStorage.getItem(this.storageKey) ?? 'null');

            if (saved && Number.isFinite(saved.sources) && Number.isFinite(saved.studio)) {
                this.sourcesWidth = saved.sources;
                this.studioWidth = saved.studio;
            }
        } catch (error) {
            // localStorage bị chặn: giữ kích thước mặc định trong CSS.
        }
    },

    persist() {
        try {
            window.localStorage.setItem(this.storageKey, JSON.stringify({
                sources: this.sourcesWidth,
                studio: this.studioWidth,
            }));
        } catch (error) {
            // Không lưu được thì bề rộng chỉ giữ trong phiên này.
        }
    },

    clamp() {
        const [minSources, maxSources] = this.limits.sources;
        const [minStudio, maxStudio] = this.limits.studio;
        const available = (this.$refs.panes?.clientWidth ?? 0) - this.limits.chat;

        this.sourcesWidth = Math.min(maxSources, Math.max(minSources, this.sourcesWidth));
        this.studioWidth = Math.min(maxStudio, Math.max(minStudio, this.studioWidth));

        if (available > 0 && this.sourcesWidth + this.studioWidth > available) {
            const overflow = this.sourcesWidth + this.studioWidth - available;
            this.sourcesWidth = Math.max(minSources, this.sourcesWidth - overflow);
            this.studioWidth = Math.max(minStudio, this.studioWidth - overflow);
        }
    },

    reset() {
        this.sourcesWidth = 300;
        this.studioWidth = 380;
        this.clamp();
        this.persist();
    },

    startDrag(side, event) {
        this.dragging = side;
        this.startX = event.clientX;
        this.startValue = side === 'sources' ? this.sourcesWidth : this.studioWidth;
        document.body.style.cursor = 'col-resize';
    },

    onPointerMove(event) {
        if (! this.dragging) {
            return;
        }

        const delta = event.clientX - this.startX;
        const value = this.dragging === 'sources' ? this.startValue + delta : this.startValue - delta;

        if (this.dragging === 'sources') {
            this.sourcesWidth = value;
        } else {
            this.studioWidth = value;
        }

        this.clamp();
    },

    onPointerUp() {
        if (! this.dragging) {
            return;
        }

        this.dragging = null;
        document.body.style.removeProperty('cursor');
        this.persist();
    },

    nudge(side, delta) {
        if (side === 'sources') {
            this.sourcesWidth += delta;
        } else {
            this.studioWidth += delta;
        }

        this.clamp();
        this.persist();
    },
});

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
