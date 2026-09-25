<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;

class GradingService
{
    /**
     * Chấm tự động trắc nghiệm và điền khuyết, giữ nguyên tự luận để chấm tay.
     */
    public function gradeAttempt(ExamAttempt $attempt): void
    {
        $attempt->loadMissing('answers.question.options');

        foreach ($attempt->answers as $answer) {
            $this->gradeAnswer($answer);
        }

        $attempt->recomputeScore();

        $attempt->forceFill([
            'submitted_at' => $attempt->submitted_at ?? now(),
            'status' => $attempt->hasPendingManualGrading()
                ? AttemptStatus::Submitted
                : AttemptStatus::Graded,
            'graded_at' => $attempt->hasPendingManualGrading() ? $attempt->graded_at : now(),
        ])->save();
    }

    public function gradeAnswer(AttemptAnswer $answer): void
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
            QuestionType::FillBlank => $this->gradeFillBlank($answer),
            default => false,
        };

        $answer->forceFill([
            'is_correct' => $isCorrect,
            'awarded_points' => $isCorrect ? (float) $question->points : 0,
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

    protected function normalize(string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return mb_strtolower($collapsed);
    }
}
