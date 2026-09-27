<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropUnique(['exam_id', 'student_id']);
            $table->unsignedTinyInteger('attempt_no')->default(1);
            $table->unsignedInteger('time_spent_seconds')->nullable();
            $table->unique(['exam_id', 'student_id', 'attempt_no']);
        });
    }

    public function down(): void
    {
        // Giữ lại lần làm mới nhất của mỗi học sinh trước khi khôi phục ràng buộc cũ.
        DB::statement('DELETE FROM exam_attempts WHERE id NOT IN (
            SELECT id FROM (
                SELECT MAX(attempt_no) AS keep_no, exam_id, student_id
                FROM exam_attempts
                GROUP BY exam_id, student_id
            ) AS keep
            WHERE keep.exam_id = exam_attempts.exam_id
              AND keep.student_id = exam_attempts.student_id
              AND keep.keep_no = exam_attempts.attempt_no
        )');

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropUnique(['exam_id', 'student_id', 'attempt_no']);
            $table->dropColumn(['attempt_no', 'time_spent_seconds']);
            $table->unique(['exam_id', 'student_id']);
        });
    }
};
