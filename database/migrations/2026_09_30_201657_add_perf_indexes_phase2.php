<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index cho các đường dẫn truy vấn nóng còn lại sau Phase 1:
     * ClassStats/Dashboard scope subject, examDueSoon, receipts, notifications.
     */
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->index(['subject_id', 'status']);
            $table->index(['exam_id', 'student_id', 'status']);
            $table->index(['subject_id', 'submitted_at']);
        });

        Schema::table('assignment_receipts', function (Blueprint $table) {
            $table->index(['assignment_id', 'completed_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->index(['subject_id', 'created_at']);
        });

        Schema::table('notebook_chunks', function (Blueprint $table) {
            $table->index(['source_id', 'position', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('notebook_chunks', function (Blueprint $table) {
            $table->dropIndex(['source_id', 'position', 'id']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['subject_id', 'created_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        Schema::table('assignment_receipts', function (Blueprint $table) {
            $table->dropIndex(['assignment_id', 'completed_at']);
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropIndex(['subject_id', 'status']);
            $table->dropIndex(['exam_id', 'student_id', 'status']);
            $table->dropIndex(['subject_id', 'submitted_at']);
        });
    }
};
