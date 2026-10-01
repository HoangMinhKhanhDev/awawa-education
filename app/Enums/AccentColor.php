<?php

namespace App\Enums;

/**
 * Màu điểm nhấn cho giao diện. Giá trị lưu trong `users.accent`: mã preset
 * hoặc mã hex tùy chỉnh (`#rrggbb`); null/rỗng nghĩa là mặc định hệ thống.
 *
 * Mỗi màu là một thang đầy đủ để ghi đè biến `--color-brand-*` mà Tailwind đã
 * sinh sẵn, nên mọi chỗ dùng `bg-brand-*`/`text-brand-*` đổi màu theo mà không
 * cần build lại CSS.
 */
enum AccentColor: string
{
    case Blue = 'blue';
    case Violet = 'violet';
    case Emerald = 'emerald';
    case Amber = 'amber';
    case Rose = 'rose';
    case Orange = 'orange';
    case White = 'white';

    public function label(): string
    {
        return match ($this) {
            self::Blue => 'Xanh dương',
            self::Violet => 'Tím',
            self::Emerald => 'Xanh lá',
            self::Amber => 'Vàng',
            self::Rose => 'Hồng',
            self::Orange => 'Cam',
            self::White => 'Trắng',
        };
    }

    /**
     * Màu chấm hiển thị trên nút chọn.
     */
    public function swatch(): string
    {
        return match ($this) {
            self::Blue => '#2154d6',
            self::Violet => '#7c3aed',
            self::Emerald => '#059669',
            self::Amber => '#d97706',
            self::Rose => '#e11d48',
            self::Orange => '#ea580c',
            self::White => '#e7e5e4',
        };
    }

    /**
     * @return array<string, string> biến CSS => mã màu hex
     */
    public function variables(): array
    {
        return match ($this) {
            self::Blue => [
                '--color-brand-50' => '#f4f7ff',
                '--color-brand-100' => '#e6edff',
                '--color-brand-200' => '#c8d8fb',
                '--color-brand-300' => '#9fb9f0',
                '--color-brand-400' => '#6c92e4',
                '--color-brand-500' => '#3d68d6',
                '--color-brand-600' => '#2154d6',
                '--color-brand-700' => '#1b43ab',
                '--color-brand-800' => '#173892',
                '--color-brand-900' => '#142e75',
                '--color-brand-950' => '#0d1e4e',
            ],
            self::Violet => [
                '--color-brand-50' => '#f5f3ff',
                '--color-brand-100' => '#ede9fe',
                '--color-brand-200' => '#ddd6fe',
                '--color-brand-300' => '#c4b5fd',
                '--color-brand-400' => '#a78bfa',
                '--color-brand-500' => '#8b5cf6',
                '--color-brand-600' => '#7c3aed',
                '--color-brand-700' => '#6d28d9',
                '--color-brand-800' => '#5b21b6',
                '--color-brand-900' => '#4c1d95',
                '--color-brand-950' => '#2e1065',
            ],
            self::Emerald => [
                '--color-brand-50' => '#ecfdf5',
                '--color-brand-100' => '#d1fae5',
                '--color-brand-200' => '#a7f3d0',
                '--color-brand-300' => '#6ee7b7',
                '--color-brand-400' => '#34d399',
                '--color-brand-500' => '#10b981',
                '--color-brand-600' => '#059669',
                '--color-brand-700' => '#047857',
                '--color-brand-800' => '#065f46',
                '--color-brand-900' => '#064e3b',
                '--color-brand-950' => '#022c22',
            ],
            self::Amber => [
                '--color-brand-50' => '#fffbeb',
                '--color-brand-100' => '#fef3c7',
                '--color-brand-200' => '#fde68a',
                '--color-brand-300' => '#fcd34d',
                '--color-brand-400' => '#fbbf24',
                '--color-brand-500' => '#f59e0b',
                '--color-brand-600' => '#d97706',
                '--color-brand-700' => '#b45309',
                '--color-brand-800' => '#92400e',
                '--color-brand-900' => '#78350f',
                '--color-brand-950' => '#451a03',
            ],
            self::Rose => [
                '--color-brand-50' => '#fff1f2',
                '--color-brand-100' => '#ffe4e6',
                '--color-brand-200' => '#fecdd3',
                '--color-brand-300' => '#fda4af',
                '--color-brand-400' => '#fb7185',
                '--color-brand-500' => '#f43f5e',
                '--color-brand-600' => '#e11d48',
                '--color-brand-700' => '#be123c',
                '--color-brand-800' => '#9f1239',
                '--color-brand-900' => '#881337',
                '--color-brand-950' => '#4c0519',
            ],
            self::Orange => [
                '--color-brand-50' => '#fff7ed',
                '--color-brand-100' => '#ffedd5',
                '--color-brand-200' => '#fed7aa',
                '--color-brand-300' => '#fdba74',
                '--color-brand-400' => '#fb923c',
                '--color-brand-500' => '#f97316',
                '--color-brand-600' => '#ea580c',
                '--color-brand-700' => '#c2410c',
                '--color-brand-800' => '#9a3412',
                '--color-brand-900' => '#7c2d12',
                '--color-brand-950' => '#431407',
            ],
            self::White => [
                '--color-brand-50' => '#fafaf9',
                '--color-brand-100' => '#f5f5f4',
                '--color-brand-200' => '#e7e5e4',
                '--color-brand-300' => '#d6d3d1',
                '--color-brand-400' => '#a8a29e',
                '--color-brand-500' => '#78716c',
                '--color-brand-600' => '#57534e',
                '--color-brand-700' => '#44403c',
                '--color-brand-800' => '#292524',
                '--color-brand-900' => '#1c1917',
                '--color-brand-950' => '#0c0a09',
            ],
        };
    }

    /**
     * Chuỗi khai báo CSS để chèn vào `<style>` trong layout.
     */
    public function cssDeclarations(): string
    {
        return self::declarationsFromMap($this->variables());
    }

    /**
     * @param  array<string, string>  $variables
     */
    public static function declarationsFromMap(array $variables): string
    {
        $declarations = [];

        foreach ($variables as $name => $value) {
            $declarations[] = "{$name}: {$value};";
        }

        return implode(' ', $declarations);
    }

    public static function isCustomHex(?string $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    /**
     * Dựng thang màu từ một mã hex: nhạt dần về trắng cho 50–400, giữ nguyên
     * ở 500, đậm dần về đen cho 600–950.
     *
     * @return array<string, string>
     */
    public static function scaleFromHex(string $hex): array
    {
        $base = self::hexToRgb($hex);
        $white = [255, 255, 255];
        $black = [0, 0, 0];

        $mix = fn (array $color, float $weight): string => self::rgbToHex(
            (int) round($base[0] * $weight + $color[0] * (1 - $weight)),
            (int) round($base[1] * $weight + $color[1] * (1 - $weight)),
            (int) round($base[2] * $weight + $color[2] * (1 - $weight)),
        );

        return [
            '--color-brand-50' => $mix($white, 0.08),
            '--color-brand-100' => $mix($white, 0.16),
            '--color-brand-200' => $mix($white, 0.32),
            '--color-brand-300' => $mix($white, 0.52),
            '--color-brand-400' => $mix($white, 0.75),
            '--color-brand-500' => strtolower($hex),
            '--color-brand-600' => $mix($black, 0.85),
            '--color-brand-700' => $mix($black, 0.68),
            '--color-brand-800' => $mix($black, 0.5),
            '--color-brand-900' => $mix($black, 0.35),
            '--color-brand-950' => $mix($black, 0.2),
        ];
    }

    /**
     * @return array{int, int, int}
     */
    protected static function hexToRgb(string $hex): array
    {
        return [
            (int) hexdec(substr($hex, 1, 2)),
            (int) hexdec(substr($hex, 3, 2)),
            (int) hexdec(substr($hex, 5, 2)),
        ];
    }

    protected static function rgbToHex(int $red, int $green, int $blue): string
    {
        return sprintf('#%02x%02x%02x', max(0, min(255, $red)), max(0, min(255, $green)), max(0, min(255, $blue)));
    }

    /**
     * Chuỗi khai báo CSS cho giá trị accent bất kỳ (preset, hex tùy chỉnh,
     * hoặc null/rỗng). Null khi là mặc định để layout không chèn gì.
     */
    public static function declarationsFor(?string $accent): ?string
    {
        if (blank($accent)) {
            return null;
        }

        $preset = self::tryFrom($accent);

        if ($preset instanceof self) {
            return $preset->cssDeclarations();
        }

        if (self::isCustomHex($accent)) {
            return self::declarationsFromMap(self::scaleFromHex(strtolower($accent)));
        }

        return null;
    }

    /**
     * Chuẩn hoá giá trị lưu DB: '' thành null, hex về chữ thường.
     */
    public static function normalize(?string $accent): ?string
    {
        if (blank($accent)) {
            return null;
        }

        if (self::tryFrom($accent) instanceof self) {
            return $accent;
        }

        if (self::isCustomHex($accent)) {
            return strtolower($accent);
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
