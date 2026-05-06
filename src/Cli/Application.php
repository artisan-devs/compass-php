<?php

declare(strict_types=1);

namespace Sidetours\Compass\Cli;

use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public function __construct(string $projectRoot)
    {
        parent::__construct(name: 'Compass', version: '0.1.0');

        $this->addCommand(new CheckCommand($projectRoot));
        $this->addCommand(new BaselineCommand($projectRoot));
        $this->addCommand(new PromptsCommand($projectRoot));

        $this->setDefaultCommand('check');
    }
}
