<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notebook_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notebook_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->string('url', 1000)->nullable();
            $table->nullableMorphs('ref');
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('char_count')->default(0);
            $table->string('status')->default('ready');
            $table->text('error')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['notebook_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notebook_sources');
    }
};
