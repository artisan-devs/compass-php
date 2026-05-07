<?php

declare(strict_types=1);

// Infrastructure layer is allowed to use service location.
$repository = app(\stdClass::class);
$other = resolve('alias');
