<?php

declare(strict_types=1);

$xs = [1, 2, 3];
array_map(static fn (int $x): int => $x * 2, $xs);
array_map(strtolower(...), $xs);
usort($xs, static fn (int $a, int $b): int => $a <=> $b);
