<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['exam_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sections');
    }
};
