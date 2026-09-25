<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_map_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_map_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('scene')->nullable();
            $table->string('label')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['knowledge_map_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_map_versions');
    }
};
