<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Pd\Netflow;

use Illuminate\Support\Collection;

class DynamicSegmentationResolver
{
    /**
     * Generate semua segment combinations dari outstanding data & selected dimensions.
     * Contoh: dimensions=['office_code', 'akad_code'] → [['office_code'=>'K001', 'akad_code'=>'01'], ...]
     * Empty dimensions = 1 kombinasi kosong (global aggregate)
     */
    public function generateSegmentCombinations(
        array $outstandingData,
        array $dimensions,
    ): Collection {
        if (empty($dimensions)) {
            return collect([[]]);
        }

        $combinations = collect([[]]);
        foreach ($dimensions as $dimension) {
            $values = $this->extractUniqueValues($outstandingData, $dimension);
            $combinations = $combinations->crossJoin($values)->map(
                fn ($combo) => [...($combo[0] ?? []), $dimension => $combo[1]],
            );
        }

        return $combinations;
    }

    private function extractUniqueValues(array $data, string $dimension): array
    {
        $values = [];
        array_walk_recursive($data, function ($value, $key) use ($dimension, &$values) {
            if ($key === $dimension && !in_array($value, $values, true)) {
                $values[] = $value;
            }
        });

        return $values;
    }

    /**
     * Filter data by segment criteria (match all dimensions dalam segment).
     * Contoh: segment=['office_code'=>'K001', 'akad_code'=>'01']
     */
    public function filterBySegment(array $data, array $segment): array
    {
        if (empty($segment)) {
            return $data;
        }

        return array_filter(
            $data,
            fn ($item) => $this->matchesSegment($item, $segment),
        );
    }

    private function matchesSegment(mixed $item, array $segment): bool
    {
        if (!is_array($item)) {
            return false;
        }

        foreach ($segment as $dimension => $value) {
            if (($item[$dimension] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }
}
