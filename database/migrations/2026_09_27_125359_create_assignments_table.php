<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();

            // Nội dung được giao: Exam, Document, Announcement hoặc KnowledgeMap.
            $table->morphs('assignable');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');

            // Thu hồi chỉ gỡ khỏi học sinh, dữ liệu bài làm vẫn giữ nguyên.
            $table->timestamp('recalled_at')->nullable();
            $table->foreignId('recalled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recall_reason', 500)->nullable();

            $table->dateTime('due_at')->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamps();

            $table->index(['subject_id', 'recalled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
