import { registerSW } from 'virtual:pwa-register';

registerSW({ immediate: true });

const storedTheme = () => {
    try {
        return localStorage.getItem('awawa-theme');
    } catch (error) {
        return null;
    }
};

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
