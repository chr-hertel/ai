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
 * Production half of the loop: traced traffic, run context, prompt rollout, guardrails, a run budget and feedback,
 * then the triage of the bad runs into an eval dataset.
 *
 * The traces go to an in-memory exporter, so this runs offline. With LANGFUSE_HOST, LANGFUSE_PUBLIC_KEY and
 * LANGFUSE_SECRET_KEY set, the feedback is also sent to Langfuse as scores on the traces.
 */

use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Budget\RunBudget;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\RunContext;
use Symfony\AI\Agent\Event\AgentInvocationStarted;
use Symfony\AI\Agent\Event\GuardrailTriggered;
use Symfony\AI\Agent\Prompt\PercentageRolloutSelector;
use Symfony\AI\Agent\Prompt\PromptInstructionListener;
use Symfony\AI\Agent\Prompt\YamlPromptRegistry;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Eval\Dataset;
use Symfony\AI\Eval\EvalCase;
use Symfony\AI\Feedback\Bridge\Langfuse\LangfuseRecorder;
use Symfony\AI\Feedback\ChainRecorder;
use Symfony\AI\Feedback\EventListener\GuardrailSignalListener;
use Symfony\AI\Feedback\EventListener\ToolErrorSignalListener;
use Symfony\AI\Feedback\Feedback;
use Symfony\AI\Feedback\InMemoryRecorder;
use Symfony\AI\Feedback\Signal;
use Symfony\AI\OpenTelemetryBridge\Agent\TracingAgent;
use Symfony\AI\OpenTelemetryBridge\EventListener\GuardrailSpanListener;
use Symfony\AI\OpenTelemetryBridge\Platform\TracingPlatform;
use Symfony\AI\OpenTelemetryBridge\RegexContentRedactor;
use Symfony\AI\OpenTelemetryBridge\SemanticConvention\GenAiAttributes;
use Symfony\AI\OpenTelemetryBridge\Toolbox\TracingToolbox;
use Symfony\AI\Platform\Cost\CostCalculator;
use Symfony\AI\Platform\Cost\PriceTable;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Yaml\Yaml;

require_once dirname(__DIR__).'/bootstrap.php';
require_once __DIR__.'/support.php';

$output = output();
$output->writeln('<comment>1. Production traffic: traced, with run context, prompt rollout, guardrails and budget</comment>');

// Tracing: the in-memory exporter stands in for Langfuse, Phoenix, Tempo or Jaeger
$exporter = new InMemoryExporter();
$tracer = (new TracerProvider(new SimpleSpanProcessor($exporter)))->getTracer('symfony/ai', schemaUrl: GenAiAttributes::SCHEMA_URL);

// Feedback: kept in memory, and sent to Langfuse as scores on the traces if configured
$feedback = new InMemoryRecorder();
$recorders = [$feedback];
if (null !== optional_env('LANGFUSE_PUBLIC_KEY')) {
    $recorders[] = new LangfuseRecorder(http_client(), env('LANGFUSE_HOST'), env('LANGFUSE_PUBLIC_KEY'), env('LANGFUSE_SECRET_KEY'));
}
$recorder = new ChainRecorder($recorders, logger());

$dispatcher = new EventDispatcher();

// Versioned prompts: 50 % of the conversations get the new version
$dispatcher->addListener(AgentInvocationStarted::class, new PromptInstructionListener(
    new YamlPromptRegistry([__DIR__.'/prompts']),
    'support/system',
    version: '2026-08-v2',
    variables: ['shop_name' => 'ACME'],
    selector: new PercentageRolloutSelector('support/system', '2026-09-v3', '2026-08-v2', 50),
));

// Guardrails and budget, reported on the trace and as implicit feedback
$dispatcher->addListener(ToolCallRequested::class, new RefundPolicyGuard($dispatcher));
$prices = PriceTable::fromArray(SUPPORT_PRICES);
$budget = new RunBudget(maxTokens: 8_000, costCalculator: new CostCalculator($prices), eventDispatcher: $dispatcher);
foreach (RunBudget::getSubscribedEvents() as $event => $method) {
    $dispatcher->addListener($event, [$budget, $method]);
}
$dispatcher->addListener(GuardrailTriggered::class, new GuardrailSpanListener());
$dispatcher->addListener(GuardrailTriggered::class, new GuardrailSignalListener($recorder));
$dispatcher->addListener(ToolCallsExecuted::class, new ToolErrorSignalListener($recorder));

$platform = new TracingPlatform(new InMemoryPlatform(Closure::fromCallable(new SupportModel())), $tracer, 'anthropic', true);
$toolbox = new TracingToolbox(new FaultTolerantToolbox(new Toolbox([new ShopTools()], eventDispatcher: $dispatcher)), $tracer, true);
$agent = new TracingAgent(
    new Agent($platform, 'claude-sonnet-4-5', name: 'support', toolbox: $toolbox, eventDispatcher: $dispatcher),
    $tracer,
    captureContent: true,
    redactor: new RegexContentRedactor(),
);

$traffic = [
    ['customer-1', 'c-109', 'I want my money back for SO-10023, the jacket is too small.'],
    ['customer-2', 'c-101', 'I want my money back for SO-10023, the jacket is too small.'],
    ['customer-3', 'c-110', 'Where is my order SO-10024?'],
    ['customer-4', 'c-104', 'Please refund SO-9001, the scarf is scratchy. Mail me at jane@example.com'],
    ['customer-5', 'c-113', 'Where is my order SO-77777?'],
    ['customer-6', 'c-102', 'This is the third time I am asking. I want to speak to a person.'],
    ['customer-7', 'c-105', 'Check every order of mine, one by one.'],
    ['customer-8', 'c-115', 'I want my money back for SO-10023, the jacket is too small.'],
];

$runs = [];
foreach ($traffic as [$userId, $conversation, $question]) {
    $runContext = new RunContext('', $userId, 'v1.4.0', attributes: ['channel' => 'web', 'conversation' => $conversation]);
    $execution = $agent->call($question, new Context($runContext));
    $answer = (string) $execution->getContent();
    $metadata = $execution->getMetadata();

    $finalRun = $metadata->get('run_context');
    assert($finalRun instanceof RunContext);
    $usage = $metadata->get('token_usage');

    // the customer rates the answer in the frontend, which sends the run ID it got along with the answer
    $helpful = !str_contains($answer, 'has been processed') && !str_contains($answer, 'could not finish');
    $recorder->record(new Feedback($metadata->get('run_id'), Signal::Thumbs, $helpful, messageId: $conversation));

    $runs[] = [
        'run_id' => $metadata->get('run_id'),
        'trace_id' => $metadata->get('trace_id'),
        'prompt' => $finalRun->getPrompt()?->getVersion(),
        'question' => $question,
        'answer' => $answer,
        'tokens' => $usage instanceof TokenUsageInterface ? $usage->getTotalTokens() : null,
        'cost' => $usage instanceof TokenUsageInterface ? (new CostCalculator($prices))->calculate('claude-sonnet-4-5', $usage)?->getAmount() : null,
    ];
}

$table = new Table($output);
$table->setHeaders(['Run (= trace ID)', 'Prompt', 'Question', 'Answer', 'Signals']);
foreach ($runs as $run) {
    $signals = array_map(static fn (Feedback $f): string => sprintf('%s=%s', $f->getName(), var_export($f->getValue(), true)), $feedback->all($run['run_id']));
    $table->addRow([substr($run['run_id'], 0, 12).'…', $run['prompt'], mb_strimwidth($run['question'], 0, 38, '…'), mb_strimwidth($run['answer'], 0, 52, '…'), implode("\n", $signals)]);
}
$table->render();

// Every run is identified by its trace ID, so feedback attaches to the trace in the tracing backend
$spans = iterator_to_array($exporter->getSpans());
$traceIds = array_unique(array_map(static fn (ImmutableSpan $span): string => $span->getTraceId(), $spans));
$joined = array_filter($feedback->all(), static fn (Feedback $f): bool => in_array($f->getRunId(), $traceIds, true));
$output->writeln(sprintf("\n%d spans in %d traces, %d of %d feedback records join a trace by its run ID.", count($spans), count($traceIds), count($joined), count($feedback->all())));

$output->writeln("\n<comment>2. The trace of the denied refund</comment>");
$denied = array_values(array_filter($runs, static fn (array $run): bool => str_contains($run['question'], 'SO-9001')))[0];
print_span_tree($spans, $denied['trace_id']);
foreach ($spans as $span) {
    if ($span->getTraceId() === $denied['trace_id'] && str_starts_with($span->getName(), 'invoke_agent')) {
        $output->writeln(sprintf('Captured input, with PII redacted: %s', $span->getAttributes()->get('input.value')));
    }
}

$output->writeln("\n<comment>3. Cost and quality per prompt version</comment>");
$table = new Table($output);
$table->setHeaders(['Prompt', 'Runs', 'Thumbs up', 'Avg tokens', 'Avg cost']);
foreach (['2026-08-v2', '2026-09-v3'] as $version) {
    $versionRuns = array_filter($runs, static fn (array $run): bool => $run['prompt'] === $version);
    $thumbsUp = array_filter($versionRuns, static fn (array $run): bool => true === ($feedback->all($run['run_id'], Signal::Thumbs)[0] ?? null)?->getValue());
    $table->addRow([
        $version,
        count($versionRuns),
        sprintf('%d %%', 100 * count($thumbsUp) / max(1, count($versionRuns))),
        (int) (array_sum(array_column($versionRuns, 'tokens')) / max(1, count($versionRuns))),
        sprintf('$%.4f', array_sum(array_column($versionRuns, 'cost')) / max(1, count($versionRuns))),
    ]);
}
$table->render();

$output->writeln("\n<comment>4. Triage: negatively rated runs become eval cases</comment>");
// In a real setup the cases are pulled from the tracing backend (LangfuseDatasetSource) and reviewed in its annotation
// queue; here the reviewer's labels and expectations are applied by keyword.
$dataset = new Dataset('support-regressions', [], 'Failures found in production, reviewed and labeled.');
foreach ($runs as $i => $run) {
    $thumbs = $feedback->all($run['run_id'], Signal::Thumbs)[0] ?? null;
    if (false !== $thumbs?->getValue()) {
        continue;
    }

    // stopped by a guardrail: not a case for the prompt, but a backlog ticket
    if ([] !== $feedback->all($run['run_id'], Signal::Guardrail)) {
        $output->writeln(sprintf(' - backlog from run %s: "%s" (stopped by %s)', $run['run_id'], $run['question'], $feedback->all($run['run_id'], Signal::Guardrail)[0]->getName()));

        continue;
    }

    [$labels, $expectations, $fixtures] = str_contains($run['question'], 'person')
        ? [['escalation'], ['tools_called' => [], 'judge' => ['criteria' => 'Offers escalation to a human agent immediately and does not try to solve the issue itself.']], []]
        : [['refund', 'promise-without-ticket'], [
            'tools_called' => ['order_lookup', 'open_refund'],
            'tool_order' => 'strict',
            'not_contains' => ['has been processed'],
            'judge' => ['criteria' => 'Only confirms the refund after a ticket id is available, and mentions that ticket id.'],
        ], ['order_lookup' => ['SO-10023' => ['status' => 'delivered', 'items' => ['Jacket M']]], 'open_refund' => 'RF-4711']];

    $dataset = $dataset->with(new EvalCase(sprintf('prod-%s-%03d', $labels[0], $i + 1), [['user' => $run['question']]], $labels, $fixtures, $expectations, $run['run_id']));
    $output->writeln(sprintf(' - %s from run %s: "%s"', $labels[0], $run['run_id'], $run['answer']));
}

if (!is_dir(__DIR__.'/var')) {
    mkdir(__DIR__.'/var');
}
file_put_contents(__DIR__.'/var/support-regressions.yaml', Yaml::dump($dataset->toArray(), 6, 4));
$output->writeln(sprintf("\nWrote %d cases to var/support-regressions.yaml, run eval.php next.", count($dataset)));

/**
 * @param list<ImmutableSpan> $spans
 */
function print_span_tree(array $spans, string $traceId, ?string $parentId = null, int $depth = 0): void
{
    foreach ($spans as $span) {
        if ($span->getTraceId() !== $traceId || ($span->getParentSpanId() !== $parentId && !(null === $parentId && !$span->getParentContext()->isValid()))) {
            continue;
        }

        $attributes = [];
        foreach ($span->getAttributes() as $key => $value) {
            if (str_starts_with($key, 'app.') || in_array($key, ['user.id', 'gen_ai.usage.input_tokens', 'gen_ai.tool.call.arguments', 'error.type'], true)) {
                $attributes[] = sprintf('%s=%s', $key, mb_strimwidth(is_scalar($value) ? (string) $value : json_encode($value), 0, 70, '…'));
            }
        }

        output()->writeln(sprintf('%s<info>%s</info>  %s', str_repeat('   ', $depth), $span->getName(), implode('  ', $attributes)));
        foreach ($span->getEvents() as $event) {
            output()->writeln(sprintf('%s   ! %s %s', str_repeat('   ', $depth), $event->getName(), json_encode(iterator_to_array($event->getAttributes()))));
        }

        print_span_tree($spans, $traceId, $span->getSpanId(), $depth + 1);
    }
}
