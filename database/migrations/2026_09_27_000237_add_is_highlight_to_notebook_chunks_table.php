<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notebook_chunks', function (Blueprint $table) {
            $table->boolean('is_highlight')->default(false)->after('content');
            $table->index(['source_id', 'is_highlight']);
        });
    }

    public function down(): void
    {
        Schema::table('notebook_chunks', function (Blueprint $table) {
            $table->dropIndex(['source_id', 'is_highlight']);
            $table->dropColumn('is_highlight');
        });
    }
};
