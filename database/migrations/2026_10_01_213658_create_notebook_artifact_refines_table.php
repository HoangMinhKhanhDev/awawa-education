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
        Schema::create('notebook_artifact_refines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artifact_id')->constrained('notebook_artifacts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Yêu cầu của giáo viên, vd "làm khó câu 3 và 7 lên".
            $table->text('instruction');
            // Tóm tắt những gì AI đã sửa, để hiện trong lịch sử.
            $table->text('summary')->nullable();
            // Đề xuất sửa của AI: JSON theo từng loại nội dung, xem ArtifactRefiner.
            $table->json('proposal')->nullable();
            // Ghi chú cho đề xuất bị bỏ qua một phần (vd "câu 99 không tồn tại").
            $table->text('note')->nullable();
            // pending: chờ duyệt; applied: đã áp vào bản nháp; dismissed: đã bỏ qua.
            $table->string('status', 20)->default('pending');
            $table->string('provider_key')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('tokens')->default(0);
            $table->timestamps();

            $table->index(['artifact_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notebook_artifact_refines');
    }
};
