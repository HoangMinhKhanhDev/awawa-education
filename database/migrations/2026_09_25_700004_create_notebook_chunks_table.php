<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notebook_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notebook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('notebook_sources')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->mediumText('content');
            $table->unsignedInteger('char_start')->default(0);
            $table->unsignedInteger('char_end')->default(0);
            $table->timestamps();

            $table->index(['notebook_id', 'source_id']);
            $table->index(['source_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notebook_chunks');
    }
};
