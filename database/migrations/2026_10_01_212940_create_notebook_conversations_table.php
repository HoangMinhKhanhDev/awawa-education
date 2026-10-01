<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notebook_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notebook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200)->default('Cuộc trò chuyện mới');
            $table->timestamps();

            $table->index(['notebook_id', 'updated_at']);
        });

        Schema::table('notebook_messages', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->constrained('notebook_conversations')->nullOnDelete();
            $table->index(['conversation_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notebook_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
        });

        Schema::dropIfExists('notebook_conversations');
    }
};
