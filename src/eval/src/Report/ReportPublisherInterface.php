<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Report;

/**
 * Pushes the results of a run to a backend, e.g. as experiment of an observability platform.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ReportPublisherInterface
{
    public function publish(Report $report): void;
}
