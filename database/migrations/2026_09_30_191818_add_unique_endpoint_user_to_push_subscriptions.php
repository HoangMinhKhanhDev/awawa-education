<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trước đây khoá theo `endpoint` nên cùng một máy mà đổi tài khoản sẽ đè
        // bản ghi cũ. Host có thể còn bản ghi trùng từ lần chạy trước, nên gộp lại
        // trước khi tạo unique index, không thì migration dừng giữa chừng.
        $duplicates = DB::table('push_subscriptions')
            ->select('endpoint', 'user_id')
            ->groupBy('endpoint', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $pair) {
            $keep = DB::table('push_subscriptions')
                ->where('endpoint', $pair->endpoint)
                ->where('user_id', $pair->user_id)
                ->max('id');

            DB::table('push_subscriptions')
                ->where('endpoint', $pair->endpoint)
                ->where('user_id', $pair->user_id)
                ->where('id', '<>', $keep)
                ->delete();
        }

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->unique(['endpoint', 'user_id'], 'push_endpoint_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropUnique('push_endpoint_user_unique');
        });
    }
};
