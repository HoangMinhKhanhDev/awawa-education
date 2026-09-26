<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notebook_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notebook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->json('payload')->nullable();
            $table->mediumText('text_content')->nullable();
            $table->string('status')->default('draft');
            $table->nullableMorphs('ref');
            $table->timestamps();

            $table->index(['notebook_id', 'type']);
            $table->index(['subject_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notebook_artifacts');
    }
};
