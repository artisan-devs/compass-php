<?php

declare(strict_types=1);

namespace Sidetours\Compass\Reporters;

use Sidetours\Compass\Engine\Result;
use Symfony\Component\Console\Output\OutputInterface;

interface Reporter
{
    public function report(Result $result, OutputInterface $output, string $projectRoot): void;
}
