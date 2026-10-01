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
        Schema::create('notebook_chunk_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chunk_id')->constrained('notebook_chunks')->cascadeOnDelete();
            $table->foreignId('notebook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('notebook_sources')->cascadeOnDelete();
            $table->string('term', 100);
            $table->unsignedSmallInteger('tf');
            $table->boolean('in_title')->default(false);

            // Truy hồi: tìm chunk chứa một trong các từ của câu hỏi.
            $table->index(['notebook_id', 'term']);
            $table->index(['source_id', 'term']);
            $table->index(['chunk_id']);
            // Chống ghi trùng khi hai request cùng nạp chỉ mục một lúc.
            $table->unique(['chunk_id', 'term', 'in_title']);
        });

        Schema::table('notebook_chunks', function (Blueprint $table) {
            // Tổng số từ trong chunk, dùng cho chuẩn hoá độ dài của BM25.
            $table->unsignedInteger('term_count')->default(0)->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notebook_chunks', function (Blueprint $table) {
            $table->dropColumn('term_count');
        });

        Schema::dropIfExists('notebook_chunk_terms');
    }
};
