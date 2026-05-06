<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use PhpParser\Node;
use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

interface Rule
{
    public function name(): string;

    public function shortDescription(): string;

    /**
     * Fully-qualified PhpParser node class names this rule wants to inspect.
     *
     * @return list<class-string<Node>>
     */
    public function nodeTypes(): array;

    /**
     * @return iterable<Violation>
     */
    public function check(Node $node, Context $context): iterable;

    /**
     * Fix prompt for this rule, ready to feed to a downstream AI agent.
     *
     * Format: YAML frontmatter (between `---` markers) followed by markdown body.
     * Frontmatter MUST include at least: `name`, `description`, `rule`.
     */
    public function fixPrompt(): string;
}
