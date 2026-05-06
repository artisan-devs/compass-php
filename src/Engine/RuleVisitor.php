<?php

declare(strict_types=1);

namespace Sidetours\Compass\Engine;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Sidetours\Compass\Rules\Rule;

final class RuleVisitor extends NodeVisitorAbstract
{
    /** @var list<Violation> */
    public array $collected = [];

    /**
     * @param array<class-string<Node>, list<Rule>> $rulesByNodeType
     */
    public function __construct(
        private readonly array $rulesByNodeType,
        private readonly Context $context,
    ) {
    }

    public function enterNode(Node $node): null
    {
        foreach ($this->rulesByNodeType as $type => $rules) {
            if (! $node instanceof $type) {
                continue;
            }
            foreach ($rules as $rule) {
                foreach ($rule->check($node, $this->context) as $violation) {
                    $this->collected[] = $violation;
                }
            }
        }

        return null;
    }
}
