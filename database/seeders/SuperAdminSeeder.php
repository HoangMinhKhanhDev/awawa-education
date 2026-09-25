<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (empty($email) || empty($password)) {
            $this->command?->warn('Bỏ qua SuperAdminSeeder: thiếu ADMIN_EMAIL hoặc ADMIN_PASSWORD trong .env');

            return;
        }

        $email = mb_strtolower(trim($email));

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            $existing->forceFill([
                'role' => Role::SuperAdmin,
                'subject_id' => null,
            ])->save();

            $this->command?->info("Super Admin đã tồn tại: {$email} (giữ nguyên mật khẩu hiện tại).");

            return;
        }

        User::create([
            'name' => env('ADMIN_NAME', 'awawa Admin'),
            'email' => $email,
            'password' => $password,
            'role' => Role::SuperAdmin,
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);

        $this->command?->info("Đã tạo Super Admin: {$email} (bắt buộc đổi mật khẩu khi đăng nhập lần đầu).");
    }
}
