@php
    $brand = config('awawa.brand.name');
@endphp

<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ config('awawa.brand.primary') }}">
    <title>{{ $brand }} — Nền tảng đội tuyển học sinh giỏi</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">

    <script>
        (function () {
            try {
                var theme = localStorage.getItem('awawa-theme');
                if (!theme) {
                    theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                if (theme === 'dark') document.documentElement.classList.add('dark');
            } catch (error) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="paper-grid min-h-full">
    <header class="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-4 sm:px-6 lg:py-6">
        <x-logo class="h-10 w-10" />
        <div class="flex items-center gap-1.5">
            <button type="button" onclick="window.awawa.toggleTheme()"
                class="flex h-10 w-10 items-center justify-center rounded-[10px] text-ink-soft hover:bg-paper-2 dark:text-slate-300 dark:hover:bg-white/5"
                aria-label="Đổi chế độ sáng/tối">
                <x-icon name="sun" class="hidden h-5 w-5 dark:block" />
                <x-icon name="moon" class="h-5 w-5 dark:hidden" />
            </button>
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-primary">Vào ứng dụng</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-ghost">Đăng nhập</a>
                <a href="{{ route('register') }}" class="btn btn-primary">Tạo tài khoản</a>
            @endauth
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl px-4 pb-16 sm:px-6">
        <section class="grid items-center gap-10 py-10 lg:grid-cols-[1.05fr_0.95fr] lg:gap-14 lg:py-16">
            <div>
                <h1 class="max-w-[18ch] font-serif text-[34px] font-semibold leading-[1.12] tracking-[-0.015em] text-ink sm:text-[42px] dark:text-white">
                    Soạn đề, giao bài, chấm điểm. Một chỗ cho cả đội tuyển.
                </h1>
                <p class="mt-5 max-w-[54ch] text-[15px] leading-relaxed text-ink-soft dark:text-slate-400">
                    Giáo viên mỗi môn có ngân hàng câu hỏi, đề thi, bài tập và tài liệu riêng. Học sinh trong đội làm bài, xem điểm và dựng sơ đồ kiến thức. Dữ liệu giữ tách biệt theo từng môn.
                </p>

                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <a href="{{ route('register') }}" class="btn btn-primary px-5 py-3">Tạo tài khoản học sinh</a>
                    <a href="{{ route('login') }}" class="btn btn-outline px-5 py-3">Đăng nhập</a>
                </div>

                <p class="mt-4 text-[13px] text-ink-faint dark:text-slate-500">
                    Học sinh tự đăng ký chỉ xem được tài liệu công khai. Giáo viên thêm vào đội tuyển thì mới làm bài và tính điểm.
                </p>
            </div>

            {{-- Vật thể nhận diện: một tờ đề đã chấm --}}
            <div class="relative">
                <div class="panel overflow-hidden">
                    <div class="flex items-center justify-between border-b border-rule px-5 py-3 dark:border-night-700">
                        <p class="text-sm font-semibold text-ink dark:text-white">Đề số 01 · Toán</p>
                        <p class="tnum text-sm text-ink-faint dark:text-slate-500">45 phút</p>
                    </div>

                    <div class="space-y-4 px-5 py-5">
                        @foreach ([
                            ['1', 'Tìm giá trị nhỏ nhất của biểu thức theo điều kiện cho trước.', null],
                            ['2', 'Chứng minh bất đẳng thức bằng phương pháp tương đương.', 'correct'],
                            ['3', 'Cho dãy số, xác định công thức tổng quát.', 'wrong'],
                        ] as [$no, $text, $mark])
                            <div class="flex gap-3">
                                <span class="tnum mt-0.5 w-4 shrink-0 text-sm text-ink-faint dark:text-slate-500">{{ $no }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm leading-relaxed text-ink dark:text-slate-200">{{ $text }}</p>
                                    @if ($mark === 'correct')
                                        <p class="mt-1 text-[13px] font-medium text-success">Đúng · 1.0 điểm</p>
                                    @elseif ($mark === 'wrong')
                                        <p class="mt-1 text-[13px] font-medium text-signal">Sai · xem lại bước biến đổi</p>
                                    @else
                                        <p class="mt-1 text-[13px] text-ink-faint dark:text-slate-500">Chưa làm</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-between border-t border-rule bg-paper-2 px-5 py-3 dark:border-night-700 dark:bg-night-900/40">
                        <p class="text-[13px] text-ink-soft dark:text-slate-400">Giáo viên chấm tự luận, nhận xét từng câu</p>
                        <p class="tnum font-serif text-xl font-semibold text-ink dark:text-white">8.5<span class="text-sm font-normal text-ink-faint">/10</span></p>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-rule pt-10 dark:border-night-700">
            <h2 class="max-w-[30ch] text-xl font-semibold text-ink dark:text-white">Trong awawa có gì</h2>
            <dl class="mt-6 grid gap-x-10 gap-y-6 sm:grid-cols-2">
                @foreach ([
                    ['Studio theo môn', 'Mỗi môn có bộ tính năng riêng và dữ liệu tách biệt, không lẫn sang nhau.'],
                    ['Ngân hàng câu hỏi', 'Trắc nghiệm, tự luận, điền khuyết — kèm đáp án, độ khó và chủ đề.'],
                    ['Đề thi và bài tập', 'Soạn từ ngân hàng, trộn câu, hẹn giờ, giao cho học sinh trong đội.'],
                    ['Chấm và xếp hạng', 'Trắc nghiệm chấm tự động, tự luận giáo viên chấm và nhận xét.'],
                    ['Sơ đồ kiến thức', 'Bảng trắng tạo node và liên kết, lưu phiên bản, xuất ảnh và PDF.'],
                    ['Thông báo', 'Có đề mới, hạn nộp và kết quả qua thông báo trong app và Web Push.'],
                ] as [$term, $desc])
                    <div class="border-t border-rule pt-4 dark:border-night-700">
                        <dt class="font-medium text-ink dark:text-slate-100">{{ $term }}</dt>
                        <dd class="mt-1 text-sm leading-relaxed text-ink-soft dark:text-slate-400">{{ $desc }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </main>

    <footer class="border-t border-rule py-8 dark:border-night-700">
        <p class="mx-auto max-w-6xl px-4 text-xs text-ink-faint sm:px-6 dark:text-slate-500">
            &copy; {{ date('Y') }} {{ $brand }} — Đội tuyển học sinh giỏi
        </p>
    </footer>
</body>
</html>
