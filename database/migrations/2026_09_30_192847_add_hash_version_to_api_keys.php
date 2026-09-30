<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('hash_version', 16)->default('hmac')->after('key_hash');
        });

        // Mọi key đã phát hành trước Phase 1 đều là SHA-256 trần → đánh dấu
        // legacy để admin biết cần xoay. Key mới (issue/rotate) luôn ghi 'hmac'.
        DB::table('api_keys')->where('hash_version', 'hmac')->update(['hash_version' => 'legacy']);
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn('hash_version');
        });
    }
};
