<?php

namespace App\Support\Accounting\Charts;

use App\Enums\Accounting\DetailType;

final class ChartTree
{
    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    public static function withoutSystemKeys(array $nodes, array $keys): array
    {
        $result = [];

        foreach ($nodes as $node) {
            if (isset($node['system_key']) && in_array($node['system_key'], $keys, true)) {
                continue;
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $node['children'] = self::withoutSystemKeys($node['children'], $keys);

                if ($node['children'] === [] && ! isset($node['system_key'])) {
                    continue;
                }
            }

            $result[] = $node;
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    public static function requireProjectDetail(array $nodes, array $keys): array
    {
        foreach ($nodes as &$node) {
            if (isset($node['system_key']) && in_array($node['system_key'], $keys, true)) {
                $levels = is_array($node['detail_levels'] ?? null) ? $node['detail_levels'] : [];
                $levels[5] = DetailType::Project;
                $node['detail_levels'] = $levels;
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $node['children'] = self::requireProjectDetail($node['children'], $keys);
            }
        }

        return $nodes;
    }
}
