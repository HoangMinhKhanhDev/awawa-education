<?php

namespace App\Support;

use App\Models\User;

/**
 * Cấu hình điều hướng theo vai trò.
 *
 * Mỗi vai trò có bộ tab riêng. Mục "primary" hiển thị trên thanh dưới cùng
 * của thiết bị di động; các mục còn lại nằm trong menu mở rộng.
 */
class Navigation
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function for(User $user): array
    {
        return match (true) {
            $user->isSuperAdmin() => [
                self::item('dashboard', 'Trang chủ', 'home', primary: true),
                self::item('info', 'Thông tin', 'bell'),
                self::item('profile', 'Hồ sơ', 'user', primary: true),
                self::item('admin.users', 'Người dùng', 'users', primary: true),
                self::item('admin.api-keys', 'API key', 'key', primary: true),
                self::item('admin.stats', 'Thống kê', 'chart', primary: true),
            ],
            $user->isTeacher() => [
                self::item('dashboard', 'Trang chủ', 'home', primary: true),
                self::item('map', 'Sơ đồ', 'map', primary: true),
                self::item('info', 'Thông tin', 'bell'),
                self::item('profile', 'Hồ sơ', 'user'),
                self::item('studio', 'Studio', 'sparkles', primary: true),
                self::item('students', 'Học sinh', 'users', primary: true),
            ],
            default => [
                self::item('dashboard', 'Trang chủ', 'home', primary: true),
                self::item('map', 'Sơ đồ', 'map', primary: true),
                self::item('info', 'Thông tin', 'bell', primary: true),
                self::item('profile', 'Hồ sơ', 'user', primary: true),
            ],
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function primary(array $items): array
    {
        return array_values(array_filter($items, fn (array $item) => $item['primary']));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function item(string $route, string $label, string $icon, bool $primary = false): array
    {
        return [
            'route' => $route,
            'label' => $label,
            'icon' => $icon,
            'url' => route($route),
            'active' => request()->routeIs($route) || request()->routeIs($route.'.*'),
            'primary' => $primary,
        ];
    }
}
