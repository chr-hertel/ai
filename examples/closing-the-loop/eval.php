<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Offline half of the loop: the golden dataset and the regressions found in production (run production.php first)
 * are run against three variants of the support agent, scored, gated and reported - then the LLM judge is
 * calibrated against human labels.
 *
 *     php closing-the-loop/eval.php                                         # all variants, exits 1 if a gate fails
 *     php closing-the-loop/eval.php --variant=baseline --variant=candidate  # the release decision for the new prompt
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Prompt\YamlPromptRegistry;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Eval\Calibration\JudgeCalibrator;
use Symfony\AI\Eval\Calibration\LabeledRun;
use Symfony\AI\Eval\Command\RunCommand;
use Symfony\AI\Eval\Dataset;
use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Eval\EvalRunner;
use Symfony\AI\Eval\Evaluator\Limits;
use Symfony\AI\Eval\Evaluator\LlmJudge;
use Symfony\AI\Eval\Evaluator\TextAssertions;
use Symfony\AI\Eval\Evaluator\ToolArguments;
use Symfony\AI\Eval\Evaluator\ToolsCalled;
use Symfony\AI\Eval\Gate\CostPerCaseGate;
use Symfony\AI\Eval\Gate\PassRateGate;
use Symfony\AI\Eval\Gate\RegressionGate;
use Symfony\AI\Eval\RecordedRun;
use Symfony\AI\Eval\Suite;
use Symfony\AI\Eval\Variant;
use Symfony\AI\Platform\Cost\CostCalculator;
use Symfony\AI\Platform\Cost\PriceTable;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;

require_once dirname(__DIR__).'/bootstrap.php';
require_once __DIR__.'/support.php';

$datasets = [Dataset::fromFile(__DIR__.'/datasets/support-golden.yaml')];
if (is_file(__DIR__.'/var/support-regressions.yaml')) {
    $datasets[] = Dataset::fromFile(__DIR__.'/var/support-regressions.yaml');
} else {
    output()->writeln('<comment>No regressions found in production yet, run production.php first to add them.</comment>');
}

$prompts = new YamlPromptRegistry([__DIR__.'/prompts']);
$judge = new LlmJudge(new InMemoryPlatform(Closure::fromCallable(new JudgeModel())), 'claude-opus-4-5');

$suite = new Suite(
    'support',
    $datasets,
    [
        new Variant('baseline', ['model' => 'claude-sonnet-4-5', 'prompt' => '2026-08-v2']),
        new Variant('candidate', ['model' => 'claude-sonnet-4-5', 'prompt' => '2026-09-v3']),
        new Variant('cheap', ['model' => 'claude-haiku-4-5', 'prompt' => '2026-09-v3']),
    ],
    // the agent under test, with the tools answered by the fixtures of the case
    static fn (Variant $variant, ?ToolboxInterface $toolbox, EvalCase $case): Agent => new Agent(
        new InMemoryPlatform(Closure::fromCallable(new SupportModel())),
        $variant->get('model'),
        name: 'support',
        instruction: $prompts->get('support/system', $variant->get('prompt'))->toInstruction(['shop_name' => 'ACME']),
        toolbox: $toolbox,
    ),
    [new ToolsCalled(), new ToolArguments(), new TextAssertions(), new Limits(), $judge],
    [
        new PassRateGate(0.9, variants: ['candidate', 'cheap']),
        new PassRateGate(0.95, 'refund', ['candidate', 'cheap']),
        new RegressionGate('baseline', 0.02),
        new CostPerCaseGate(0.05),
    ],
    repetitions: 2,
    toolCatalog: new Toolbox([new ShopTools()]),
);

output()->writeln('<comment>5. Offline evaluation: golden cases and production regressions against three variants</comment>');

$application = new Application('eval');
$application->setAutoExit(false);
$application->addCommand(new RunCommand([$suite], new EvalRunner(new CostCalculator(PriceTable::fromArray(SUPPORT_PRICES)))));

$arguments = array_slice($_SERVER['argv'], 1);
$exitCode = $application->run(new ArgvInput(['eval.php', 'ai:eval:run', 'support',
    '--report=markdown:'.__DIR__.'/var/eval/summary.md',
    '--report=junit:'.__DIR__.'/var/eval/junit.xml',
    '--report=json:'.__DIR__.'/var/eval/results.json',
    ...$arguments,
]), output());

output()->writeln("\n<comment>6. Judge calibration against human labels</comment>");

// production answers a reviewer labeled with the judge's refund criteria
$criteria = ['judge' => ['criteria' => 'Only confirms the refund after a ticket id is available, and mentions that ticket id.']];
$labeled = [];
foreach ([
    ['I opened the refund for SO-10023, your ticket is RF-4612.', true],
    ['No problem, your refund has been processed.', false],
    ['Your refund has been processed, you will get a ticket by mail.', false],
    ['I opened the refund for SO-10024, your ticket is RF-4291.', true],
    ['The refund is done.', false],
    ['Refund opened, ticket RF-4512.', true],
] as $i => [$answer, $human]) {
    $run = new RecordedRun(new MessageBag(Message::ofUser('I want my money back for SO-10023.')), new TextResult($answer));
    $labeled[] = new LabeledRun(new EvalCase('label-'.$i, '', expectations: $criteria), $run, $human);
}

$calibration = (new JudgeCalibrator())->calibrate($judge, $labeled);
output()->writeln(sprintf(' Agreement %.1f %%, Cohen\'s kappa %.2f, false pass rate %.1f %%, false fail rate %.1f %%',
    $calibration->getAgreement() * 100,
    $calibration->getKappa(),
    $calibration->getFalsePassRate() * 100,
    $calibration->getFalseFailRate() * 100,
));
foreach ($calibration->getDisagreements() as $disagreement) {
    output()->writeln(sprintf(' Disagreement on %s: human %s, judge %s - %s', $disagreement['case'], $disagreement['human'] ? 'pass' : 'fail', $disagreement['judge'] ? 'pass' : 'fail', $disagreement['explanation']));
}
output()->writeln($calibration->getKappa() >= 0.7 ? ' The judge can be trusted (kappa >= 0.7).' : ' <error>The judge is not calibrated (kappa < 0.7), improve its prompt before trusting its scores.</error>');

exit($exitCode);
