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
        Schema::table('attempt_answers', function (Blueprint $table) {
            // Đáp án 4 mệnh đề a–d của câu chùm đúng/sai, theo đúng thứ tự
            // option trong đề. Null nghĩa là bỏ trống mệnh đề đó.
            $table->json('sub_answers')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->dropColumn('sub_answers');
        });
    }
};
