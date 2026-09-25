<?php

use App\Enums\MapVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('visibility')->default(MapVisibility::Private->value);
            $table->string('share_token', 40)->nullable()->unique();
            $table->unsignedInteger('current_version')->default(0);
            $table->timestamps();

            $table->index(['subject_id', 'owner_id']);
            $table->index(['subject_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_maps');
    }
};
