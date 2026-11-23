<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Gate;

use Symfony\AI\Eval\Report\GateResult;
use Symfony\AI\Eval\Report\Report;

/**
 * A condition the results of a suite must meet, e.g. in CI before a prompt or model change is released.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface GateInterface
{
    /**
     * @return list<GateResult>
     */
    public function check(Report $report): array;
}
