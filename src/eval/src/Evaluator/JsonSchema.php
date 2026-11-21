<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Eval\Evaluator;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\EvaluatorInterface;
use Symfony\AI\Eval\Exception\RuntimeException;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Eval\Score;
use Symfony\AI\Platform\Result\ObjectResult;

/**
 * Checks that the final answer is valid JSON matching the JSON schema in "expect.json_schema".
 *
 * Structured output is validated as it is, a text answer must be plain JSON without Markdown code fences.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class JsonSchema implements EvaluatorInterface
{
    private readonly Validator $validator;

    public function __construct()
    {
        if (!class_exists(Validator::class)) {
            throw new RuntimeException('For using the JsonSchema evaluator, the opis/json-schema package is required. Try running "composer require opis/json-schema".');
        }

        $this->validator = new Validator(null, 5, false);
    }

    public function getName(): string
    {
        return 'json_schema';
    }

    public function evaluate(EvalCase $case, RecordedRun $run): ?Score
    {
        $schema = $case->expectation('json_schema');
        if (!\is_array($schema) || [] === $schema) {
            return null;
        }

        $result = $run->getResult();
        if ($result instanceof ObjectResult) {
            $data = Helper::toJSON($result->getContent());
        } elseif (null === $answer = $run->getAnswer()) {
            return Score::fail($this->getName(), 'No answer to validate.');
        } else {
            try {
                $data = json_decode($answer, false, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                return Score::fail($this->getName(), \sprintf('The answer is not valid JSON: %s.', $exception->getMessage()));
            }
        }

        $error = $this->validator->validate($data, Helper::toJSON($schema))->error();
        if (null === $error) {
            return Score::pass($this->getName());
        }

        $failures = [];
        foreach ((new ErrorFormatter())->format($error, false) as $path => $message) {
            $failures[] = \sprintf('%s: %s', $path, $message);
        }

        return Score::fail($this->getName(), \sprintf('The answer does not match the schema: %s', implode('; ', $failures)));
    }
}
