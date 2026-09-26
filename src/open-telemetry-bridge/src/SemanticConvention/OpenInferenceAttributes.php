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
 * OpenInference attributes emitted next to the GenAI ones, as a workaround for Arize Phoenix.
 *
 * Phoenix shows the root span's "input.value" and "output.value" in its session view, but does not derive them from
 * gen_ai.input.messages and gen_ai.output.messages (checked with Phoenix 20.16, see
 * https://github.com/Arize-ai/phoenix/issues/10622). Remove this class and its usage in TracingAgent once Phoenix
 * does, as the bridge is meant to emit the GenAI semantic conventions only.
 *
 * @internal
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class OpenInferenceAttributes
{
    public const INPUT_VALUE = 'input.value';
    public const OUTPUT_VALUE = 'output.value';
}
