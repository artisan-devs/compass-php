<?php

declare(strict_types=1);

namespace Sidetours\Compass\Rules;

use Sidetours\Compass\Engine\Context;
use Sidetours\Compass\Engine\Violation;

/**
 * Optional companion to {@see Rule} for checks that operate on a whole file rather than on AST nodes.
 *
 * Use cases:
 *  - Detecting MISSING source-level constructs (e.g. a top-level `declare(strict_types=1);`),
 *    which a node visitor cannot see because there is no node to visit.
 *  - Path-scoped emissions that don't naturally fit the per-node model.
 *
 * Implementing classes still implement {@see Rule} (so they appear in reports under their `name()`,
 * carry a `fixPrompt()`, etc.). The Runner calls `checkFile()` once per scanned file, alongside
 * the AST-based `check()` invocations.
 *
 * If the rule has no AST work to do, return an empty array from {@see Rule::nodeTypes()}.
 */
interface FileRule
{
    /**
     * @return iterable<Violation>
     */
    public function checkFile(Context $context): iterable;
}
