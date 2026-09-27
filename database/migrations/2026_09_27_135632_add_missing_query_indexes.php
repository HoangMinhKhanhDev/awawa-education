<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bổ sung index cho các đường dẫn truy vấn nóng mà trước đây phải quét
     * toàn bảng. Đã kiểm bằng EXPLAIN QUERY PLAN trên SQLite:
     *
     *   exam_attempts WHERE student_id = ?  -> SCAN exam_attempts
     *   exam_attempts WHERE status = ?      -> SCAN exam_attempts
     *   exams WHERE subject_id = ? AND status = ? -> SEARCH, nhưng chỉ dùng
     *       được subject_id vì cột status nằm sau cột type trong index cũ.
     */
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->index('student_id');
            $table->index('status');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->index(['subject_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex(['subject_id', 'status']);
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropIndex('exam_attempts_student_id_index');
            $table->dropIndex('exam_attempts_status_index');
        });
    }
};
