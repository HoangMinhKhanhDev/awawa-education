<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('base_url')->nullable();
            $table->text('api_key')->nullable();
            $table->string('default_model')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('config')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['is_enabled', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
