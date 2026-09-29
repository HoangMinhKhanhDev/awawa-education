<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use Illuminate\Support\Collection;

class GradingService
{
    /**
     * Chấm tự động trắc nghiệm và điền khuyết, giữ nguyên tự luận để chấm tay.
     */
    public function gradeAttempt(ExamAttempt $attempt): void
    {
        $attempt->loadMissing('answers.question.options');

        // Điểm của câu tính theo đề (bảng pivot), không phải điểm gốc của câu
        // trong ngân hàng — giáo viên có thể cho câu 1 điểm trong đề 10 điểm
        // nhưng 2 điểm trong đề khác.
        $pivotPoints = ExamQuestion::query()
            ->where('exam_id', $attempt->exam_id)
            ->pluck('points', 'question_id');

        foreach ($attempt->answers as $answer) {
            $this->gradeAnswer($answer, $pivotPoints);
        }

        $attempt->recomputeScore();

        $submittedAt = $attempt->submitted_at ?? now();

        $attempt->forceFill([
            'submitted_at' => $submittedAt,
            'time_spent_seconds' => $attempt->started_at !== null
                ? max(0, (int) $attempt->started_at->diffInSeconds($submittedAt))
                : null,
            'status' => $attempt->hasPendingManualGrading()
                ? AttemptStatus::Submitted
                : AttemptStatus::Graded,
            'graded_at' => $attempt->hasPendingManualGrading() ? $attempt->graded_at : now(),
        ])->save();
    }

    /**
     * @param  Collection<int, float>|null  $pivotPoints  điểm từng câu trong đề, khoá theo question_id
     */
    public function gradeAnswer(AttemptAnswer $answer, ?Collection $pivotPoints = null): void
    {
        $question = $answer->question;

        if ($question === null) {
            return;
        }

        if ($question->type === QuestionType::Essay) {
            return;
        }

        $isCorrect = match ($question->type) {
            QuestionType::MultipleChoice => $this->gradeMultipleChoice($answer),
            QuestionType::TrueFalse => $this->gradeTrueFalse($answer),
            QuestionType::FillBlank => $this->gradeFillBlank($answer),
            default => false,
        };

        $pivot = $pivotPoints?->get($answer->question_id);

        // Đề cũ có thể để trống điểm từng câu thì giữ điểm gốc ngân hàng.
        $points = $pivot !== null ? (float) $pivot : (float) $question->points;

        $answer->forceFill([
            'is_correct' => $isCorrect,
            'awarded_points' => $isCorrect ? $points : 0,
        ])->save();
    }

    protected function gradeMultipleChoice(AttemptAnswer $answer): bool
    {
        $correct = $answer->question->options
            ->where('is_correct', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $selected = collect($answer->selected_option_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        return $correct !== [] && $correct === $selected;
    }

    protected function gradeFillBlank(AttemptAnswer $answer): bool
    {
        $expected = $this->normalize((string) $answer->question->answer);
        $given = $this->normalize((string) $answer->answer_text);

        return $expected !== '' && $expected === $given;
    }

    /**
     * Câu đúng/sai so trên giá trị đã chuẩn hoá, nên "Đúng" và "true" vẫn là một.
     */
    protected function gradeTrueFalse(AttemptAnswer $answer): bool
    {
        $expected = QuestionType::normalizeTruthy((string) $answer->question->answer);
        $given = QuestionType::normalizeTruthy((string) $answer->answer_text);

        return $expected !== null && $expected === $given;
    }

    protected function normalize(string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return mb_strtolower($collapsed);
    }
}
