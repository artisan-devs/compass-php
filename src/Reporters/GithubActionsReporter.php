<?php

declare(strict_types=1);

namespace Sidetours\Compass\Reporters;

use Sidetours\Compass\Engine\Result;
use Symfony\Component\Console\Output\OutputInterface;

final class GithubActionsReporter implements Reporter
{
    public function report(Result $result, OutputInterface $output, string $projectRoot): void
    {
        $root = rtrim($projectRoot, '/').'/';
        foreach ($result->violations as $violation) {
            $relative = str_starts_with($violation->file, $root) ? substr($violation->file, strlen($root)) : $violation->file;
            $output->writeln(sprintf(
                '::error file=%s,line=%d,title=%s::%s',
                $relative,
                $violation->line,
                $violation->rule,
                str_replace(["\n", "\r"], ' ', $violation->message),
            ));
        }

        foreach ($result->errors as $error) {
            $output->writeln('::error::'.$error);
        }
    }
}
