<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Chạy một lệnh Artisan ở tiến trình CLI tách rời khỏi web request.
 *
 * Lý do tồn tại: shared hosting (Hostinger) chặn `proc_open` trong PHP-FPM, nên
 * không mở được tiến trình con để chạy lệnh gọi AI. `dispatch()->afterResponse()`
 * cũng không giải quyết được vì Laravel vẫn chạy job đồng bộ trong cùng tiến trình
 * PHP (xem `Illuminate\Bus\Dispatcher::dispatchAfterResponse` gọi `dispatchSync`).
 *
 * Ba tầng theo thứ tự ưu tiên, xem `Studio::startGeneration`:
 *   1. tiến trình con bằng `proc_open` — tách hẳn, không bị giới hạn thời gian;
 *   2. `defer()` — gửi response trước rồi soạn nốt, chỉ cần FastCGI;
 *   3. hàng chờ cho cron — luôn khả dụng nhưng phải chờ tới phút tiếp theo.
 *
 * Cách dùng: chỉ truyền lệnh Artisan với tham số không do người dùng nhập.
 */
class BackgroundProcess
{
    /**
     * Máy chủ có cho phép mở tiến trình con không.
     */
    public function available(): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        if (in_array('proc_open', $disabled, true)) {
            return false;
        }

        return true;
    }

    /**
     * Mở tiến trình nền và trả về ngay, không chờ nó kết thúc.
     *
     * @param  list<string>  $arguments
     * @return bool true nếu đã khởi chạy được tiến trình
     */
    public function start(string $command, array $arguments = []): bool
    {
        // Test không được mở tiến trình thật: tiến trình con sẽ nối vào database
        // thật chứ không phải database của test. Giả lập thành công để các test
        // về luồng soạn nội dung vẫn chạy được bình thường.
        if (app()->runningUnitTests()) {
            return true;
        }

        if (! $this->available()) {
            return false;
        }

        $parts = array_merge([$command], $arguments);

        $quoted = $this->quoteAll($parts);

        // POSIX cần `nohup ... &` để tiến trình sống tiếp sau khi shell cha thoát.
        // Windows thì con không bị giết khi tiến trình cha kết thúc, nên gọi thẳng là đủ.
        $line = $this->isWindows()
            ? $quoted
            : 'nohup '.$quoted.' > /dev/null 2>&1 &';

        $descriptors = [
            0 => ['file', $this->nullDevice(), 'r'],
            1 => ['file', $this->nullDevice(), 'a'],
            2 => ['file', $this->nullDevice(), 'a'],
        ];

        try {
            $process = @proc_open($line, $descriptors, $pipes, base_path());
        } catch (\Throwable $exception) {
            Log::warning('Không mở được tiến trình nền.', ['exception' => $exception->getMessage()]);

            return false;
        }

        if (! is_resource($process)) {
            return false;
        }

        return true;
    }

    /**
     * Chạy $work sau khi phản hồi đã gửi xong cho trình duyệt.
     *
     * Đây là cách duy nhất để "tạo tức thì" khi hosting chặn `proc_open`: gọi
     * `fastcgi_finish_request()` để trình duyệt nhận trọn response ngay, rồi PHP
     * chạy nốt phần soạn còn lại. Giáo viên thấy màn "đang soạn" sau vài trăm
     * mili giây thay vì vài chục giây.
     *
     * Khác `dispatch()->afterResponse()`: cái đó Laravel vẫn chạy job đồng bộ
     * trong chính web request nên trình duyệt vẫn phải chờ.
     */
    public function defer(callable $work): bool
    {
        if (! function_exists('fastcgi_finish_request')) {
            return false;
        }

        app()->terminating(function () use ($work): void {
            fastcgi_finish_request();

            $work();
        });

        return true;
    }

    /**
     * Đường dẫn PHP CLI đang chạy ứng dụng.
     */
    public function phpBinary(): string
    {
        $binary = (string) (defined('PHP_BINARY') ? PHP_BINARY : '');

        if ($binary !== '') {
            return $binary;
        }

        return PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
    }

    public function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /**
     * Bọc mỗi tham số trong dấu nháy kép và thoát ký tự đặc biệt của shell.
     *
     * @param  list<string>  $parts
     */
    private function quoteAll(array $parts): string
    {
        return implode(' ', array_map(fn (string $part): string => $this->quote($part), $parts));
    }

    private function quote(string $value): string
    {
        if ($this->isWindows()) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return "'".str_replace("'", "'\\''", $value)."'";
    }

    private function nullDevice(): string
    {
        return $this->isWindows() ? 'NUL' : '/dev/null';
    }
}
