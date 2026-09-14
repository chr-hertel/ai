<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Fireworks;

use Symfony\AI\Platform\Bridge\Generic\EmbeddingsClient as GenericEmbeddingsClient;
use Symfony\AI\Platform\Bridge\Generic\Transport\HttpTransport;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EmbeddingsClient extends GenericEmbeddingsClient
{
    public function __construct(HttpTransport $transport)
    {
        parent::__construct($transport, modelClass: Fireworks::class, tokenUsageExtractor: new TokenUsageExtractor());
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Fireworks && $model->supports(Capability::EMBEDDINGS);
    }
}
