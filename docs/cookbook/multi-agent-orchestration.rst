.. card:
    title: Multi-Agent Orchestration
    description: Route questions to specialist agents with a central orchestrator.
    icon: network
    components: Agent

Multi-Agent Orchestration
=========================

Sometimes a single AI agent is not enough. You may need specialists for different domains, with
an orchestrator that routes questions to the right expert. In this guide you will build a
multi-agent system where a central orchestrator delegates tasks to specialist agents based on
the content of the user's question.

Prerequisites
-------------

* Symfony AI Platform component
* Symfony AI Agent component

Step 1: Install Packages
------------------------

Install the Platform and Agent components via Composer::

    composer require symfony/ai-platform symfony/ai-agent

Step 2: Create Specialist Agents
--------------------------------

Each specialist agent is a regular :class:`Symfony\\AI\\Agent\\Agent` with an ``instruction`` that defines its
area of expertise. Give each agent a descriptive ``name`` so the orchestrator can identify it::

    use Symfony\AI\Agent\Agent;

    $technical = new Agent(
        $platform,
        'gpt-5-mini',
        instruction: 'You are a technical support specialist. Help users resolve bugs and errors.',
        name: 'technical',
    );

    $billing = new Agent(
        $platform,
        'gpt-5-mini',
        instruction: 'You are a billing specialist. Help users with invoices and payments.',
        name: 'billing',
    );

Step 3: Create an Orchestrator Agent
------------------------------------

The orchestrator is another agent whose system prompt instructs it to analyze user questions and
decide which specialist should handle them. It does not need a name since it acts as the entry
point::

    $orchestrator = new Agent(
        $platform,
        'gpt-5-mini',
        instruction: 'You are an agent orchestrator that routes user questions to specialized agents.',
    );

Step 4: Configure Handoffs
--------------------------

A :class:`Symfony\\AI\\Agent\\Handoff\\Handoff` defines when a question should be routed to a specific agent. Its
description is what the orchestrator's model reads to make its decision, so describe the agent's domain in natural
language::

    use Symfony\AI\Agent\Handoff\Handoff;

    $handoffs = [
        new Handoff($technical, 'bugs, errors, exceptions and other technical problems'),
        new Handoff($billing, 'invoices, payments, billing and subscriptions'),
        new Handoff($fallback, 'general or otherwise unmatched requests'),
    ];

A handoff can also carry a condition, so it is only offered when it applies::

    new Handoff($billing, 'invoices and payments', condition: fn () => $user->hasSubscription());

Step 5: Give the Handoffs to the Orchestrator
---------------------------------------------

Handoffs live on the orchestrating agent. Before it answers itself, it asks the model which of its handoffs should
handle the request; when none is picked, it simply answers on its own::

    $fallback = new Agent(
        $platform,
        'gpt-5-mini',
        instruction: 'You are a general assistant. Help users with any non-specialized questions.',
        name: 'fallback',
    );

    $multiAgent = new Agent(
        $platform,
        'gpt-5-mini',
        instruction: 'You are an agent orchestrator that routes user questions to specialized agents.',
        handoffs: $handoffs,
    );

Step 6: Route Questions Automatically
-------------------------------------

Call the multi-agent with a ``MessageBag`` just like a regular agent. The orchestrator analyzes
the question and routes it to the appropriate specialist automatically::

    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    // Technical question - routed to the technical agent
    $messages = new MessageBag(
        Message::ofUser('I get a "Call to undefined method" error in my controller.'),
    );
    $result = $multiAgent->call($messages);
    echo $result->getContent();

    // General question - routed to the fallback agent
    $messages = new MessageBag(
        Message::ofUser('Can you recommend a good pasta recipe?'),
    );
    $result = $multiAgent->call($messages);
    echo $result->getContent();

.. tip::

    You can add as many specialist agents as you need. To observe the routing, iterate the agent's execution with
    ``run()``: the handoff is reported as a ``Progress`` update of the ``handoff`` stage. The
    ``HandoffRequested`` and ``HandoffCompleted`` events carry the same information, and a listener on
    ``HandoffRequested`` can override or cancel the target.

Learn More
----------

* :doc:`../components/agent` - Processors, memory, and advanced agent patterns
* :doc:`../bundles/ai-bundle` - Automatic wiring in Symfony applications
