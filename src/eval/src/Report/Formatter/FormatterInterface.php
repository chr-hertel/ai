<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Report\Formatter;

use Symfony\AI\Eval\Report\Report;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface FormatterInterface
{
    public function format(Report $report): string;
}
