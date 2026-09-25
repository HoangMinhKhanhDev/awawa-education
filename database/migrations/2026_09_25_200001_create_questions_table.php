<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->text('content');
            $table->text('answer')->nullable();
            $table->text('explanation')->nullable();
            $table->string('difficulty')->default('medium');
            $table->decimal('points', 5, 2)->default(1);
            $table->string('topic')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['subject_id', 'type']);
            $table->index(['subject_id', 'difficulty']);
            $table->index(['subject_id', 'topic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
