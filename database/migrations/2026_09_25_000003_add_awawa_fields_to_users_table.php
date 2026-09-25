<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default(Role::Student->value)->after('password');
            $table->foreignId('subject_id')->nullable()->after('role')->constrained('subjects')->nullOnDelete();
            $table->string('google_id')->nullable()->unique()->after('subject_id');
            $table->string('avatar')->nullable()->after('google_id');
            $table->boolean('must_change_password')->default(false)->after('avatar');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');

            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropIndex(['role']);
            $table->dropColumn([
                'role',
                'subject_id',
                'google_id',
                'avatar',
                'must_change_password',
                'last_login_at',
            ]);
        });
    }
};
