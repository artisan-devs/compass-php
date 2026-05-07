<?php

declare(strict_types=1);

if (str_contains($email, '@')) {
}
if (str_starts_with($name, 'admin_')) {
}
$pos = strpos($email, '@');
$slice = substr($email, 0, strpos($email, '@'));
