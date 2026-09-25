<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_section_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->decimal('points', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'question_id']);
            $table->index(['exam_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_questions');
    }
};
