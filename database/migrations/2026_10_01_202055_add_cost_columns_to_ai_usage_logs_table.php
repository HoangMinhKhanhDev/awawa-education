<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            // Chi phí tính bằng micro-dollar (1 USD = 1.000.000) để không lệch
            // float. Null nghĩa là chưa có giá của model, khác với 0 là miễn phí.
            $table->unsignedBigInteger('cost_micros')->nullable()->after('total_tokens');
            // Giá đã dùng để tính, tính theo 1 triệu token, để đối chiếu sau này.
            $table->unsignedBigInteger('price_prompt_micros')->nullable()->after('cost_micros');
            $table->unsignedBigInteger('price_completion_micros')->nullable()->after('price_prompt_micros');
            // Token prompt được phục vụ từ cache của nhà cung cấp, để đo mức độ
            // prompt caching có hiệu quả.
            $table->unsignedInteger('cached_prompt_tokens')->default(0)->after('price_completion_micros');
            $table->unsignedInteger('cache_creation_tokens')->default(0)->after('cached_prompt_tokens');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropColumn([
                'cost_micros',
                'price_prompt_micros',
                'price_completion_micros',
                'cached_prompt_tokens',
                'cache_creation_tokens',
            ]);
        });
    }
};
