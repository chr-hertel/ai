<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\Model;

/**
 * A model queried with an {@see \Symfony\AI\Platform\Bridge\Decision\Evaluation} through the Decisions API.
 *
 * GPT-6 Luna answers on the Responses API as well, so its decision use comes from the {@see Decision\DecisionModelCatalog}.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Decision extends Model
{
}
