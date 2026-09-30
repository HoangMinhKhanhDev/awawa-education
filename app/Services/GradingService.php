<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GradingService
{
    /**
     * Chấm tự động trắc nghiệm và điền khuyết, giữ nguyên tự luận để chấm tay.
     */
    public function gradeAttempt(ExamAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt): void {
            $locked = ExamAttempt::query()->whereKey($attempt->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== AttemptStatus::InProgress) {
                return;
            }

            $locked->loadMissing('answers.question.options');

            // Điểm của câu tính theo đề (bảng pivot), không phải điểm gốc của câu
            // trong ngân hàng — giáo viên có thể cho câu 1 điểm trong đề 10 điểm
            // nhưng 2 điểm trong đề khác.
            $pivotPoints = ExamQuestion::query()
                ->where('exam_id', $locked->exam_id)
                ->pluck('points', 'question_id');

            // Gom 1 upsert duy nhất thay vì N UPDATE tuần tự (đề 50 câu = 50
            // write nối tiếp, dễ `database is locked` khi nhiều HS nộp cùng lúc).
            $now = now();
            $rows = [];
            $auto = 0.0;
            $manual = 0.0;
            $hasPendingManual = false;

            foreach ($locked->answers as $answer) {
                $scored = $this->scoreAnswer($answer, $pivotPoints);

                if ($scored === null) {
                    $hasPendingManual = true;

                    continue;
                }

                [$isCorrect, $awarded] = $scored;

                $rows[] = [
                    'attempt_id' => $locked->getKey(),
                    'question_id' => $answer->question_id,
                    'is_correct' => $isCorrect,
                    'awarded_points' => $awarded,
                    'updated_at' => $now,
                    'created_at' => $answer->created_at ?? $now,
                ];

                if ($answer->question?->type === QuestionType::Essay) {
                    $manual += $awarded;
                } else {
                    $auto += $awarded;
                }
            }

            if ($rows !== []) {
                AttemptAnswer::query()->upsert(
                    $rows,
                    ['attempt_id', 'question_id'],
                    ['is_correct', 'awarded_points', 'updated_at']
                );
            }

            $submittedAt = $locked->submitted_at ?? $now;

            $locked->forceFill([
                'auto_score' => $auto,
                'manual_score' => $manual,
                'score' => $auto + $manual,
                'max_score' => (float) ($locked->exam->total_points ?? $locked->max_score),
                'submitted_at' => $submittedAt,
                'time_spent_seconds' => $locked->started_at !== null
                    ? max(0, (int) $locked->started_at->diffInSeconds($submittedAt))
                    : null,
                'status' => $hasPendingManual
                    ? AttemptStatus::Submitted
                    : AttemptStatus::Graded,
                'graded_at' => $hasPendingManual ? $locked->graded_at : $now,
            ])->save();

            StudentAbility::forgetCache($locked->subject_id);
        });
    }

    /**
     * Tính điểm 1 câu mà không ghi DB. Trả về null khi câu chờ chấm tay
     * (tự luận). Câu mất dữ liệu gốc tính 0 điểm như trước đây.
     *
     * @param  Collection<int, float>|null  $pivotPoints  điểm từng câu trong đề, khoá theo question_id
     * @return array{0: bool, 1: float}|null
     */
    public function scoreAnswer(AttemptAnswer $answer, ?Collection $pivotPoints = null): ?array
    {
        $question = $answer->question;

        if ($question === null) {
            return [false, 0.0];
        }

        if ($question->type === QuestionType::Essay) {
            return null;
        }

        $pivot = $pivotPoints?->get($answer->question_id);

        // Đề cũ có thể để trống điểm từng câu thì giữ điểm gốc ngân hàng.
        $points = $pivot !== null ? (float) $pivot : (float) $question->points;

        if ($question->type === QuestionType::TrueFalseCluster) {
            $awarded = $this->gradeTrueFalseCluster($answer, $points);

            return [$points > 0 && $awarded >= $points, $awarded];
        }

        $isCorrect = match ($question->type) {
            QuestionType::MultipleChoice => $this->gradeMultipleChoice($answer),
            QuestionType::TrueFalse => $this->gradeTrueFalse($answer),
            QuestionType::FillBlank => $this->gradeFillBlank($answer),
            default => false,
        };

        return [$isCorrect, $isCorrect ? $points : 0.0];
    }

    /**
     * @param  Collection<int, float>|null  $pivotPoints  điểm từng câu trong đề, khoá theo question_id
     */
    public function gradeAnswer(AttemptAnswer $answer, ?Collection $pivotPoints = null): void
    {
        $scored = $this->scoreAnswer($answer, $pivotPoints);

        if ($scored === null) {
            return;
        }

        [$isCorrect, $awarded] = $scored;

        $answer->forceFill([
            'is_correct' => $isCorrect,
            'awarded_points' => $awarded,
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

    /**
     * Chấm chùm đúng/sai chuẩn BGD theo số mệnh đề đúng: 0–1 đúng được 0,
     * 2 đúng được 1/4, 3 đúng được 1/2, cả 4 đúng được trọn điểm chùm.
     * Mệnh đề bỏ trống tính là sai. Chùm không đủ 4 mệnh đề thì 0 điểm.
     */
    protected function gradeTrueFalseCluster(AttemptAnswer $answer, float $points): float
    {
        $expected = $answer->question->options
            ->sortBy('order')
            ->values()
            ->map(fn ($option): bool => (bool) $option->is_correct);

        if ($expected->count() !== 4) {
            return 0.0;
        }

        $given = collect($answer->sub_answers ?? [])->take(4)->values();

        $correct = 0;

        foreach ($expected->all() as $index => $truth) {
            $choice = QuestionType::normalizeTruthy((string) ($given->get($index) ?? ''));

            if ($choice === null) {
                continue;
            }

            if (($choice === QuestionType::TRUE) === $truth) {
                $correct++;
            }
        }

        $factor = match ($correct) {
            4 => 1.0,
            3 => 0.5,
            2 => 0.25,
            default => 0.0,
        };

        return round($points * $factor, 2);
    }

    protected function normalize(string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return mb_strtolower($collapsed);
    }
}
