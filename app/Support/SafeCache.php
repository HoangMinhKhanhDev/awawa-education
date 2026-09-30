<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache có kiểm soát: giá trị unserialize hỏng thì coi như cache miss.
 *
 * Host chạy `file` cache nên `Cache::remember()` serialize giá trị. Sau mỗi lần
 * deploy, class trong PHP đổi phiên bản và các giá trị cache cũ unserialize ra
 * `__PHP_Incomplete_Class`. Chỗ nào có kiểu trả về nghiêm ngặt (vd `: Collection`)
 * sẽ ném TypeError và làm trắng cả trang; chỗ nào không có kiểu thì lỗi lộ ra
 * muộn ở tầng view, kiểu `Attempt to read property "read_at" on string`.
 *
 * Lớp này giữ đúng hai điều đơn giản:
 *   1. cache **mảng dữ liệu thô**, không cache object Eloquent. Mảng thì không
 *      chứa tên class nên unserialize không bao giờ hỏng;
 *   2. nếu đọc ra `__PHP_Incomplete_Class` (cache do code cũ ghi) thì bỏ qua và
 *      tính lại, thay vì để giá trị hỏng chảy tiếp vào app.
 */
class SafeCache
{
    /**
     * @template TValue
     *
     * @param  string  $key
     * @param  int|\DateTimeInterface|\DateInterval  $ttl
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public static function remember(string $key, int|\DateTimeInterface|\DateInterval $ttl, Closure $callback): mixed
    {
        $value = Cache::get($key);

        if ($value !== null && ! self::isBroken($value)) {
            return $value;
        }

        if ($value !== null) {
            // Cache cũ lưu object của class đã đổi. Xoá để lần sau còn ghi lại
            // đúng kiểu dữ liệu mới.
            Cache::forget($key);
        }

        $fresh = $callback();

        Cache::put($key, $fresh, $ttl);

        return $fresh;
    }

    /**
     * Giá trị lấy từ cache có dùng được không.
     *
     * Mảng thô luôn dùng được nên đây là cách nhanh nhất cho phần lớn chỗ.
     */
    public static function isBroken(mixed $value): bool
    {
        if (! is_object($value)) {
            return false;
        }

        if ($value instanceof \__PHP_Incomplete_Class) {
            return true;
        }

        if ($value instanceof \Illuminate\Support\Collection) {
            foreach ($value as $item) {
                if (self::isBroken($item)) {
                    return true;
                }
            }
        }

        return false;
    }
}
