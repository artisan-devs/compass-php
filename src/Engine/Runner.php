<?php

declare(strict_types=1);

namespace Sidetours\Compass\Engine;

use PhpParser\Error as ParserError;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Sidetours\Compass\Rules\Rule;

final class Runner
{
    public function __construct(
        private readonly Configuration $config,
        private readonly IgnoreList $ignoreList,
        private readonly FileScanner $scanner = new FileScanner(),
    ) {
    }

    public function run(): Result
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $rulesByNodeType = $this->indexRulesByNodeType();

        $violations = [];
        $ignored = [];
        $errors = [];
        $files = 0;

        foreach ($this->scanner->scan($this->config->paths, $this->config->exclude, $this->config->projectRoot) as $file) {
            $files++;
            $source = @file_get_contents($file);
            if ($source === false) {
                $errors[] = sprintf('Could not read %s', $file);
                continue;
            }

            try {
                $ast = $parser->parse($source);
            } catch (ParserError $e) {
                $errors[] = sprintf('%s: %s', $file, $e->getMessage());
                continue;
            }
            if ($ast === null) {
                continue;
            }

            $context = new Context($file, $source);
            $collector = new RuleVisitor($rulesByNodeType, $context);

            $traverser = new NodeTraverser();
            $traverser->addVisitor($collector);
            $traverser->traverse($ast);

            foreach ($collector->collected as $violation) {
                if ($context->isIgnored($violation->rule, $violation->line) || $this->ignoreList->isIgnored($violation)) {
                    $ignored[] = $violation;
                    continue;
                }
                $violations[] = $violation;
            }
        }

        return new Result($violations, $ignored, $errors, $files);
    }

    /**
     * @return array<class-string<Node>, list<Rule>>
     */
    private function indexRulesByNodeType(): array
    {
        $index = [];
        foreach ($this->config->rules as $rule) {
            foreach ($rule->nodeTypes() as $type) {
                $index[$type][] = $rule;
            }
        }

        return $index;
    }

}
