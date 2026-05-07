<?php

declare(strict_types=1);

class Owner
{
    public function run(array $names): array
    {
        $a = array_map([$this, 'normalize'], $names);
        $b = array_filter($names, 'is_string');
        usort($names, [Owner::class, 'compare']);

        return [$a, $b];
    }

    public function normalize(string $n): string
    {
        return $n;
    }

    public static function compare(string $a, string $b): int
    {
        return $a <=> $b;
    }
}
