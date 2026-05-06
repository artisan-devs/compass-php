<?php

declare(strict_types=1);

namespace Sidetours\Compass\Reporters;

use Sidetours\Compass\Engine\Result;
use Symfony\Component\Console\Output\OutputInterface;

final class JsonReporter implements Reporter
{
    public const GROUP_BY_NONE = 'none';
    public const GROUP_BY_FILE = 'file';
    public const GROUP_BY_RULE = 'rule';

    public function __construct(private readonly string $groupBy = self::GROUP_BY_NONE)
    {
        if (! in_array($this->groupBy, [self::GROUP_BY_NONE, self::GROUP_BY_FILE, self::GROUP_BY_RULE], true)) {
            throw new \InvalidArgumentException(sprintf('Unknown group-by mode "%s". Use none|file|rule.', $this->groupBy));
        }
    }

    public function report(Result $result, OutputInterface $output, string $projectRoot): void
    {
        $violations = array_map(static fn ($v) => $v->toArray(), $result->violations);

        $payload = [
            'filesScanned' => $result->filesScanned,
            'groupedBy' => $this->groupBy,
            'violations' => $this->groupBy === self::GROUP_BY_NONE ? $violations : $this->group($violations),
            'ignored' => array_map(static fn ($v) => $v->toArray(), $result->ignored),
            'errors' => $result->errors,
        ];

        $output->writeln((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param list<array{rule:string,message:string,file:string,line:int}> $violations
     * @return array<string, list<array{rule:string,message:string,file:string,line:int}>>
     */
    private function group(array $violations): array
    {
        $key = $this->groupBy;
        $out = [];
        foreach ($violations as $v) {
            $out[$v[$key]][] = $v;
        }
        ksort($out);

        return $out;
    }
}
