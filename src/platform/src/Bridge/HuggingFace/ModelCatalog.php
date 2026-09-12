<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;

/**
 * HuggingFace supports a wide range of models dynamically; models are
 * identified by repository/model format (e.g., "microsoft/DialoGPT-medium").
 *
 * Because the catalog has no static knowledge of which task each model
 * actually supports, every task client accepts every model and the user picks
 * one via `$options['endpoint']`. chat_completion is the default, being both
 * the most common consumer-facing case and the first client the provider
 * registers.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends FallbackModelCatalog
{
}
