{{--
    Bootstrap theme phải chạy trước @vite để không có nháy sáng (FOUC) khi tải trang.

    Ba trạng thái được lưu ở localStorage: 'light' | 'dark' | 'system'.
    Không có giá trị hợp lệ (hoặc bị chặn) thì coi như 'system'.

    Biến resolved dùng cho: class .dark trên <html>, thuộc tính data-theme để CSS
    chọn icon, color-scheme để trình duyệt vẽ scrollbar/control native theo theme,
    và <meta name="theme-color"> cho thanh hệ thống trên mobile.
--}}
@php
    $brandPrimary = config('awawa.brand.primary');
    $darkSurface = '#0e1118';
@endphp
<script>
    (function () {
        var stored = null;

        try {
            stored = localStorage.getItem('awawa-theme');
        } catch (error) {
            stored = null;
        }

        if (stored !== 'light' && stored !== 'dark') {
            stored = 'system';
        }

        var system = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        var resolved = stored === 'system' ? system : stored;
        var root = document.documentElement;

        root.classList.toggle('dark', resolved === 'dark');
        root.dataset.theme = stored;
        root.style.colorScheme = resolved;

        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', resolved === 'dark' ? @json($darkSurface) : @json($brandPrimary));
        }
    })();
</script>
