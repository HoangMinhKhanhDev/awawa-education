<?php

namespace App\Support;

/**
 * Dựng cây sơ đồ tư duy từ danh sách node phẳng {id, label, parent}.
 *
 * Chịu được dữ liệu xấu của AI: parent không tồn tại, tự tham chiếu và chu
 * trình đều không làm treo hay lặp node — node nào cũng hiện đúng một lần.
 */
class MindMapTree
{
    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array{node: array<string, mixed>, depth: int, children: array}>
     */
    public static function build(array $nodes): array
    {
        $byId = [];

        foreach ($nodes as $node) {
            if (! is_array($node) || ! isset($node['id'])) {
                continue;
            }

            $id = (string) $node['id'];

            if ($id === '' || isset($byId[$id])) {
                continue;
            }

            $byId[$id] = $node;
        }

        if ($byId === []) {
            return [];
        }

        $childrenOf = [];
        $roots = [];

        foreach ($byId as $id => $node) {
            $parent = $node['parent'] ?? null;
            $parent = is_scalar($parent) ? trim((string) $parent) : null;

            if ($parent !== null && $parent !== '' && $parent !== $id && isset($byId[$parent])) {
                $childrenOf[$parent][] = $id;
            } else {
                $roots[] = $id;
            }
        }

        if ($roots === []) {
            $roots = [array_key_first($byId)];
        }

        $visited = [];

        $build = function (string $id, int $depth) use (&$build, $byId, $childrenOf, &$visited): ?array {
            if ($depth > 30 || isset($visited[$id])) {
                return null;
            }

            $visited[$id] = true;

            $children = [];

            foreach ($childrenOf[$id] ?? [] as $childId) {
                $child = $build($childId, $depth + 1);

                if ($child !== null) {
                    $children[] = $child;
                }
            }

            return ['node' => $byId[$id], 'depth' => $depth, 'children' => $children];
        };

        $tree = [];

        foreach ($roots as $rootId) {
            $item = $build($rootId, 0);

            if ($item !== null) {
                $tree[] = $item;
            }
        }

        return $tree;
    }
}
