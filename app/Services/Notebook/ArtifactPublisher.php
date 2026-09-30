<?php

namespace App\Services\Notebook;

use App\Enums\ArtifactType;
use App\Enums\Difficulty;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\MapVisibility;
use App\Enums\QuestionType;
use App\Models\Document;
use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\KnowledgeMap;
use App\Models\KnowledgeMapVersion;
use App\Models\NotebookArtifact;
use App\Models\Question;
use App\Services\NotificationDispatcher;
use App\Support\MindMapTree;
use App\Support\TrueFalseClusterMerger;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Xuất bản artefact thành dữ liệu thật của môn (câu hỏi/đề/tài liệu/sơ đồ).
 */
class ArtifactPublisher
{
    public function publish(NotebookArtifact $artifact, bool $isPublic = true, bool $deliverExam = false): void
    {
        // Bản nháp cũ lưu từng câu đúng/sai rời rạc: gom thành chùm 4 mệnh đề
        // trước khi tạo dữ liệu thật, để đề mới luôn đúng chuẩn BGD.
        $payload = $artifact->payload ?? [];
        $converted = TrueFalseClusterMerger::convertPayload($payload);

        if ($converted !== $payload) {
            $artifact->update(['payload' => $converted]);
        }

        $subjectId = $artifact->subject_id;
        $userId = $artifact->user_id ?? auth()->id();
        $type = ArtifactType::from($artifact->type);

        match ($type) {
            ArtifactType::Questions => $this->publishQuestions($artifact, $subjectId, $userId),
            ArtifactType::Exam => $this->publishExam($artifact, $subjectId, $userId, $deliverExam),
            ArtifactType::MindMap => $this->publishMindMap($artifact, $userId),
            default => $this->publishDocument($artifact, $subjectId, $userId, $isPublic),
        };
    }

    protected function publishQuestions(NotebookArtifact $artifact, int $subjectId, ?int $userId): void
    {
        $items = array_values(array_filter($artifact->payload['items'] ?? [], fn (array $item): bool => ($item['included'] ?? true) !== false));

        if ($items === []) {
            throw new \RuntimeException('Chọn ít nhất một câu hỏi để xuất bản.');
        }

        foreach ($items as $item) {
            $this->createQuestion($item, $subjectId, $userId);
        }

        $artifact->forceFill(['status' => 'published', 'ref_type' => null, 'ref_id' => null])->save();
    }

    protected function publishExam(NotebookArtifact $artifact, int $subjectId, ?int $userId, bool $deliver = false): void
    {
        $settings = is_array($artifact->payload['settings'] ?? null) ? $artifact->payload['settings'] : [];

        $sections = array_values(array_filter(array_map(function (array $section): array {
            $section['questions'] = array_values(array_filter(
                $section['questions'] ?? [],
                fn (array $question): bool => ($question['included'] ?? true) !== false,
            ));

            return $section;
        }, $artifact->payload['sections'] ?? []), fn (array $section): bool => $section['questions'] !== []));

        if ($sections === []) {
            throw new \RuntimeException('Chọn ít nhất một câu hỏi trong đề để xuất bản.');
        }

        $exam = Exam::create([
            'subject_id' => $subjectId,
            'created_by' => $userId,
            'type' => ExamType::Exam,
            'title' => $artifact->title,
            'description' => $artifact->payload['description'] ?? null,
            'duration_minutes' => filled($settings['duration_minutes'] ?? null)
                ? max(1, (int) $settings['duration_minutes'])
                : null,
            'shuffle_questions' => (bool) ($settings['shuffle_questions'] ?? false),
            'shuffle_options' => (bool) ($settings['shuffle_options'] ?? false),
            'status' => $deliver ? ExamStatus::Published : ExamStatus::Draft,
        ]);

        $order = 0;

        foreach ($sections as $sectionIndex => $section) {
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

        if ($deliver) {
            app(NotificationDispatcher::class)->examPublished($exam);
        }
    }

    protected function publishDocument(NotebookArtifact $artifact, int $subjectId, ?int $userId, bool $isPublic): void
    {
        $slug = Str::slug($artifact->title) ?: 'tai-lieu-ai';
        $path = 'notebook/ai/'.$slug.'-'.Str::lower(Str::random(6)).'.md';
        $content = $artifact->type === ArtifactType::Flashcards->value
            ? $this->flashcardsToMarkdown($artifact->payload['cards'] ?? [], $artifact->title)
            : (string) ($artifact->text_content ?? '');

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

    /**
     * @param  array<int, array{front?: string, back?: string}>  $cards
     */
    protected function flashcardsToMarkdown(array $cards, string $title): string
    {
        $lines = ['# '.$title];

        foreach ($cards as $index => $card) {
            $lines[] = '## Thẻ '.($index + 1);
            $lines[] = '**Mặt trước:** '.(string) ($card['front'] ?? '');
            $lines[] = '**Mặt sau:** '.(string) ($card['back'] ?? '');
        }

        return implode("\n\n", $lines);
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

        if ($type === QuestionType::TrueFalseCluster) {
            $statements = array_values(array_filter(
                (array) ($item['options'] ?? []),
                fn (array $option): bool => filled($option['content'] ?? null),
            ));

            // Chùm thiếu mệnh đề sẽ chấm 0 cho mọi học sinh (GradingService),
            // chặn ngay tại đây thay vì xuất bản một câu hỏng.
            if (count($statements) !== 4) {
                throw new \RuntimeException('Chùm đúng/sai “'.Str::limit((string) ($item['content'] ?? ''), 60, '…').'” cần đúng 4 mệnh đề có nội dung.');
            }

            foreach ($statements as $index => $option) {
                $question->options()->create([
                    'content' => (string) $option['content'],
                    'is_correct' => QuestionType::normalizeTruthy((string) ($option['is_correct'] ?? '')) === QuestionType::TRUE,
                    'order' => $index,
                ]);
            }
        }

        return $question;
    }

    /**
     * Xếp cây gọn theo chiều dọc: lá xếp liền nhau, nút cha nằm giữa các con,
     * màu và cỡ chữ theo tầng. Cây lấy từ MindMapTree nên parent lạ hay chu
     * trình đều không làm lệch bố cục.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    protected function buildScene(array $nodes): array
    {
        $tree = MindMapTree::build($nodes);

        $nodeWidth = 220;
        $hGap = 90;
        $vGap = 40;

        $backgrounds = ['#fff3bf', '#e7f5ff', '#e5dbff', '#d3f9d8', '#ffe8cc'];

        $sizes = [];
        $measure = function (array $item) use (&$measure, &$sizes): void {
            $id = (string) ($item['node']['id'] ?? '');
            $lines = $this->wrapLabel((string) ($item['node']['label'] ?? ''));
            $sizes[$id] = ['lines' => $lines, 'height' => 30 + count($lines) * 22];

            foreach ($item['children'] as $child) {
                $measure($child);
            }
        };

        foreach ($tree as $root) {
            $measure($root);
        }

        $positions = [];
        $cursor = 40;

        $layout = function (array $item, int $depth) use (&$layout, &$positions, &$cursor, $nodeWidth, $hGap, $vGap, $sizes): float {
            $id = (string) ($item['node']['id'] ?? '');
            $height = $sizes[$id]['height'] ?? 70;
            $x = $depth * ($nodeWidth + $hGap) + 40;

            if ($item['children'] === []) {
                $y = $cursor;
                $cursor += $height + $vGap;
            } else {
                $centers = [];

                foreach ($item['children'] as $child) {
                    $centers[] = $layout($child, $depth + 1);
                }

                $y = (min($centers) + max($centers)) / 2 - $height / 2;
            }

            $positions[$id] = ['x' => $x, 'y' => $y, 'depth' => $depth];

            return $y + $height / 2;
        };

        foreach ($tree as $root) {
            $layout($root, 0);
        }

        $elements = [];

        foreach ($sizes as $id => $size) {
            $position = $positions[$id] ?? ['x' => 40, 'y' => 40, 'depth' => 0];
            $depth = $position['depth'];
            $height = $size['height'];
            $lines = $size['lines'];

            $elements[] = [
                'type' => 'rectangle', 'id' => 'rect-'.$id, 'x' => $position['x'], 'y' => $position['y'],
                'width' => $nodeWidth, 'height' => $height, 'angle' => 0,
                'strokeColor' => '#1e1e1e', 'backgroundColor' => $backgrounds[$depth % count($backgrounds)], 'fillStyle' => 'solid',
                'strokeWidth' => $depth === 0 ? 3 : 2, 'strokeStyle' => 'solid', 'roughness' => 1, 'opacity' => 100,
                'groupIds' => [], 'roundness' => ['type' => 3], 'seed' => random_int(1, 100000), 'version' => 1,
                'versionNonce' => random_int(1, 100000), 'isDeleted' => false, 'boundElements' => null,
                'updated' => 1, 'link' => null, 'locked' => false,
            ];

            $fontSize = $depth === 0 ? 20 : ($depth === 1 ? 18 : 16);
            $textHeight = count($lines) * $fontSize * 1.25;

            $elements[] = [
                'type' => 'text', 'id' => 'text-'.$id, 'x' => $position['x'] + 10, 'y' => $position['y'] + ($height - $textHeight) / 2,
                'width' => $nodeWidth - 20, 'height' => $textHeight, 'angle' => 0,
                'strokeColor' => '#1e1e1e', 'backgroundColor' => 'transparent', 'fillStyle' => 'solid',
                'strokeWidth' => 2, 'strokeStyle' => 'solid', 'roughness' => 1, 'opacity' => 100,
                'groupIds' => [], 'roundness' => null, 'seed' => random_int(1, 100000), 'version' => 1,
                'versionNonce' => random_int(1, 100000), 'isDeleted' => false, 'boundElements' => null,
                'updated' => 1, 'link' => null, 'locked' => false,
                'fontSize' => $fontSize, 'fontFamily' => 5, 'text' => implode("\n", $lines),
                'textAlign' => 'center', 'verticalAlign' => 'middle', 'containerId' => null,
                'originalText' => implode("\n", $lines), 'lineHeight' => 1.25,
            ];
        }

        foreach ($positions as $id => $position) {
            $parent = $this->mindMapParent($nodes, (string) $id);

            if ($parent === null || ! isset($positions[$parent])) {
                continue;
            }

            $from = $positions[$parent];
            $fromHeight = $sizes[$parent]['height'] ?? 70;
            $toHeight = $sizes[$id]['height'] ?? 70;

            $sx = $from['x'] + $nodeWidth;
            $sy = $from['y'] + $fromHeight / 2;
            $ex = $position['x'];
            $ey = $position['y'] + $toHeight / 2;

            $elements[] = [
                'type' => 'arrow', 'id' => 'arrow-'.$parent.'-'.$id, 'x' => $sx, 'y' => $sy,
                'width' => $ex - $sx, 'height' => $ey - $sy, 'angle' => 0,
                'strokeColor' => '#868e96', 'backgroundColor' => 'transparent', 'fillStyle' => 'solid',
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

    /**
     * Ngắt nhãn dài thành nhiều dòng để chữ không tràn khỏi khung.
     *
     * @return list<string>
     */
    protected function wrapLabel(string $label, int $perLine = 26, int $maxLines = 3): array
    {
        $label = trim(preg_replace('/\s+/u', ' ', $label) ?? '');

        if ($label === '') {
            return [''];
        }

        $lines = [];
        $current = '';

        foreach (preg_split('/ /u', $label) ?: [] as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if (mb_strlen($candidate) <= $perLine) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
            }

            $current = $word;

            if (count($lines) >= $maxLines - 1) {
                break;
            }
        }

        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        }

        $lines = array_slice(array_values($lines), 0, $maxLines);

        if (count($lines) === $maxLines && mb_strlen($label) > mb_strlen(implode(' ', $lines))) {
            $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1]).'…';
        }

        return $lines === [] ? [''] : array_values($lines);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    protected function mindMapParent(array $nodes, string $id): ?string
    {
        foreach ($nodes as $node) {
            if (is_array($node) && (string) ($node['id'] ?? '') === $id) {
                $parent = $node['parent'] ?? null;

                return is_scalar($parent) && trim((string) $parent) !== '' ? (string) $parent : null;
            }
        }

        return null;
    }
}
