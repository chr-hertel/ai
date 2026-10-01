<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\OpenTelemetryBridge\SemanticConvention;

/**
 * Application attributes recorded from the agent's run context, not covered by the GenAI semantic conventions.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AppAttributes
{
    public const PREFIX = 'app.';
    public const RUN_ID = 'app.run_id';
    public const RELEASE = 'app.release';
    public const PROMPT_NAME = 'app.prompt.name';
    public const PROMPT_VERSION = 'app.prompt.version';
    public const GUARDRAIL_EVENT = 'guardrail.triggered';
}
