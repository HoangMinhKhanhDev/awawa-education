<?php

namespace App\Services\Notebook;

use App\Enums\ArtifactType;
use App\Enums\Difficulty;
use App\Enums\MapVisibility;
use App\Enums\QuestionType;
use App\Models\Document;
use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\KnowledgeMap;
use App\Models\KnowledgeMapVersion;
use App\Models\NotebookArtifact;
use App\Models\Question;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Xuất bản artefact thành dữ liệu thật của môn (câu hỏi/đề/tài liệu/sơ đồ).
 */
class ArtifactPublisher
{
    public function publish(NotebookArtifact $artifact, bool $isPublic = true): void
    {
        $subjectId = $artifact->subject_id;
        $userId = $artifact->user_id ?? auth()->id();
        $type = ArtifactType::from($artifact->type);

        match ($type) {
            ArtifactType::Questions => $this->publishQuestions($artifact, $subjectId, $userId),
            ArtifactType::Exam => $this->publishExam($artifact, $subjectId, $userId),
            ArtifactType::MindMap => $this->publishMindMap($artifact, $userId),
            default => $this->publishDocument($artifact, $subjectId, $userId, $isPublic),
        };
    }

    protected function publishQuestions(NotebookArtifact $artifact, int $subjectId, ?int $userId): void
    {
        foreach ($artifact->payload['items'] ?? [] as $item) {
            $this->createQuestion($item, $subjectId, $userId);
        }

        $artifact->forceFill(['status' => 'published', 'ref_type' => null, 'ref_id' => null])->save();
    }

    protected function publishExam(NotebookArtifact $artifact, int $subjectId, ?int $userId): void
    {
        $exam = Exam::create([
            'subject_id' => $subjectId,
            'created_by' => $userId,
            'type' => 'exam',
            'title' => $artifact->title,
            'description' => $artifact->payload['description'] ?? null,
            'status' => 'draft',
        ]);

        $order = 0;

        foreach ($artifact->payload['sections'] ?? [] as $sectionIndex => $section) {
            $examSection = ExamSection::create([
                'exam_id' => $exam->id,
                'title' => $section['title'] ?? ('Phần '.($sectionIndex + 1)),
                'instructions' => $section['instructions'] ?? null,
                'order' => $sectionIndex,
            ]);

            foreach ($section['questions'] ?? [] as $item) {
                $question = $this->createQuestion($item, $subjectId, $userId);

                $exam->examQuestions()->create([
                    'question_id' => $question->id,
                    'exam_section_id' => $examSection->id,
                    'order' => ++$order,
                    'points' => $item['points'] ?? null,
                ]);
            }
        }

        $exam->refreshTotalPoints();

        $artifact->forceFill([
            'status' => 'published',
            'ref_type' => $exam->getMorphClass(),
            'ref_id' => $exam->id,
        ])->save();
    }

    protected function publishDocument(NotebookArtifact $artifact, int $subjectId, ?int $userId, bool $isPublic): void
    {
        $slug = Str::slug($artifact->title) ?: 'tai-lieu-ai';
        $path = 'notebook/ai/'.$slug.'-'.Str::lower(Str::random(6)).'.md';
        $content = (string) ($artifact->text_content ?? '');

        Storage::disk('public')->put($path, $content);

        $document = Document::create([
            'subject_id' => $subjectId,
            'created_by' => $userId,
            'title' => $artifact->title,
            'description' => 'Tạo bằng AI từ Notebook.',
            'category' => 'AI',
            'file_path' => $path,
            'original_name' => $slug.'.md',
            'mime' => 'text/markdown',
            'size' => strlen($content),
            'is_public' => $isPublic,
        ]);

        $artifact->forceFill([
            'status' => 'published',
            'ref_type' => $document->getMorphClass(),
            'ref_id' => $document->id,
        ])->save();
    }

    protected function publishMindMap(NotebookArtifact $artifact, ?int $userId): void
    {
        $scene = $this->buildScene($artifact->payload['nodes'] ?? []);

        $map = KnowledgeMap::create([
            'subject_id' => $artifact->subject_id,
            'owner_id' => $userId,
            'title' => $artifact->title,
            'description' => 'Sơ đồ tạo bằng AI từ Notebook.',
            'visibility' => MapVisibility::Private,
            'current_version' => 1,
        ]);

        KnowledgeMapVersion::create([
            'knowledge_map_id' => $map->id,
            'version' => 1,
            'scene' => $scene,
            'label' => 'AI tạo từ Notebook',
            'created_by' => $userId,
        ]);

        $artifact->forceFill([
            'status' => 'published',
            'ref_type' => $map->getMorphClass(),
            'ref_id' => $map->id,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function createQuestion(array $item, int $subjectId, ?int $userId): Question
    {
        $type = QuestionType::tryFrom((string) ($item['type'] ?? '')) ?? QuestionType::Essay;

        $question = Question::create([
            'subject_id' => $subjectId,
            'created_by' => $userId,
            'type' => $type,
            'content' => (string) ($item['content'] ?? ''),
            'answer' => filled($item['answer'] ?? null) ? (string) $item['answer'] : null,
            'explanation' => filled($item['explanation'] ?? null) ? (string) $item['explanation'] : null,
            'difficulty' => Difficulty::tryFrom((string) ($item['difficulty'] ?? ''))?->value ?? 'medium',
            'points' => (float) ($item['points'] ?? 1),
            'topic' => filled($item['topic'] ?? null) ? (string) $item['topic'] : null,
            'is_active' => true,
        ]);

        if ($type === QuestionType::MultipleChoice) {
            foreach (array_slice((array) ($item['options'] ?? []), 0, 6) as $index => $option) {
                if (blank($option['content'] ?? null)) {
                    continue;
                }

                $question->options()->create([
                    'content' => (string) $option['content'],
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                    'order' => $index,
                ]);
            }
        }

        return $question;
    }

    /**
     * Dựng scene Excalidraw từ danh sách node (id, label, parent).
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    protected function buildScene(array $nodes): array
    {
        $labels = [];

        foreach ($nodes as $node) {
            if (isset($node['id'])) {
                $labels[(string) $node['id']] = $node;
            }
        }

        $depth = [];
        $resolveDepth = function (string $id, int $guard = 0) use (&$resolveDepth, $labels, &$depth): int {
            if (isset($depth[$id])) {
                return $depth[$id];
            }

            if ($guard > 30) {
                return 0;
            }

            $parent = $labels[$id]['parent'] ?? null;

            $depth[$id] = ($parent !== null && isset($labels[(string) $parent])) ? $resolveDepth((string) $parent, $guard + 1) + 1 : 0;

            return $depth[$id];
        };

        foreach (array_keys($labels) as $id) {
            $resolveDepth((string) $id);
        }

        $width = 220;
        $height = 70;
        $hGap = 90;
        $vGap = 40;

        $columns = [];
        $elements = [];
        $positions = [];
        $rows = [];

        foreach ($labels as $id => $node) {
            $d = $depth[$id] ?? 0;
            $rows[$d] = ($rows[$d] ?? 0);
            $x = $d * ($width + $hGap) + 40;
            $y = $rows[$d] * ($height + $vGap) + 40;
            $rows[$d]++;

            $positions[$id] = ['x' => $x, 'y' => $y];

            $seed = random_int(1, 100000);

            $elements[] = [
                'type' => 'rectangle', 'id' => 'rect-'.$id, 'x' => $x, 'y' => $y,
                'width' => $width, 'height' => $height, 'angle' => 0,
                'strokeColor' => '#1e1e1e', 'backgroundColor' => '#e7f5ff', 'fillStyle' => 'solid',
                'strokeWidth' => 2, 'strokeStyle' => 'solid', 'roughness' => 1, 'opacity' => 100,
                'groupIds' => [], 'roundness' => ['type' => 3], 'seed' => $seed, 'version' => 1,
                'versionNonce' => random_int(1, 100000), 'isDeleted' => false, 'boundElements' => null,
                'updated' => 1, 'link' => null, 'locked' => false,
            ];

            $elements[] = [
                'type' => 'text', 'id' => 'text-'.$id, 'x' => $x + 10, 'y' => $y + $height / 2 - 10,
                'width' => $width - 20, 'height' => 20, 'angle' => 0,
                'strokeColor' => '#1e1e1e', 'backgroundColor' => 'transparent', 'fillStyle' => 'solid',
                'strokeWidth' => 2, 'strokeStyle' => 'solid', 'roughness' => 1, 'opacity' => 100,
                'groupIds' => [], 'roundness' => null, 'seed' => random_int(1, 100000), 'version' => 1,
                'versionNonce' => random_int(1, 100000), 'isDeleted' => false, 'boundElements' => null,
                'updated' => 1, 'link' => null, 'locked' => false,
                'fontSize' => 16, 'fontFamily' => 5, 'text' => (string) ($node['label'] ?? ''),
                'textAlign' => 'center', 'verticalAlign' => 'middle', 'containerId' => null,
                'originalText' => (string) ($node['label'] ?? ''), 'lineHeight' => 1.25,
            ];
        }

        foreach ($labels as $id => $node) {
            $parent = $node['parent'] ?? null;

            if ($parent === null || ! isset($positions[(string) $parent])) {
                continue;
            }

            $from = $positions[(string) $parent];
            $to = $positions[$id];

            $sx = $from['x'] + $width;
            $sy = $from['y'] + $height / 2;
            $ex = $to['x'];
            $ey = $to['y'] + $height / 2;

            $elements[] = [
                'type' => 'arrow', 'id' => 'arrow-'.$parent.'-'.$id, 'x' => $sx, 'y' => $sy,
                'width' => $ex - $sx, 'height' => $ey - $sy, 'angle' => 0,
                'strokeColor' => '#1e1e1e', 'backgroundColor' => 'transparent', 'fillStyle' => 'solid',
                'strokeWidth' => 2, 'strokeStyle' => 'solid', 'roughness' => 1, 'opacity' => 100,
                'groupIds' => [], 'roundness' => ['type' => 2], 'seed' => random_int(1, 100000), 'version' => 1,
                'versionNonce' => random_int(1, 100000), 'isDeleted' => false, 'boundElements' => null,
                'updated' => 1, 'link' => null, 'locked' => false,
                'points' => [[0, 0], [$ex - $sx, $ey - $sy]], 'lastCommittedPoint' => null,
                'startBinding' => null, 'endBinding' => null, 'startArrowhead' => null, 'endArrowhead' => 'arrow',
            ];
        }

        return [
            'type' => 'excalidraw',
            'version' => 2,
            'elements' => $elements,
            'appState' => ['viewBackgroundColor' => '#ffffff', 'gridSize' => null],
            'files' => [],
        ];
    }
}
