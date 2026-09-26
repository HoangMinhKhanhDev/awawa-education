<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notebooks', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
        });

        Schema::table('notebooks', function (Blueprint $table) {
            $table->dropUnique('notebooks_owner_id_unique');
            $table->index('owner_id');
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notebooks', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
        });

        Schema::table('notebooks', function (Blueprint $table) {
            $table->dropIndex('notebooks_owner_id_index');
            $table->unique('owner_id');
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
