<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notebook_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notebook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role');
            $table->mediumText('content');
            $table->json('citations')->nullable();
            $table->string('provider_key')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('tokens')->default(0);
            $table->boolean('is_error')->default(false);
            $table->timestamps();

            $table->index(['notebook_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notebook_messages');
    }
};
