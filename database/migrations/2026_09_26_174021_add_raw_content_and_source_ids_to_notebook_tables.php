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
        Schema::table('notebook_sources', function (Blueprint $table) {
            $table->longText('raw_content')->nullable();
        });

        Schema::table('notebook_messages', function (Blueprint $table) {
            $table->json('source_ids')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notebook_messages', function (Blueprint $table) {
            $table->dropColumn('source_ids');
        });

        Schema::table('notebook_sources', function (Blueprint $table) {
            $table->dropColumn('raw_content');
        });
    }
};
