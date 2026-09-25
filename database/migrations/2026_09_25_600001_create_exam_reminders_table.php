<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('milestone', 10);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'milestone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_reminders');
    }
};
