<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Scaleway;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Feature;
use Symfony\AI\Platform\Modality;
use Symfony\AI\Platform\Task;

/**
 * @author Marcus Stöhr <marcus@fischteich.net>
 */
final class Embeddings extends Model
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(string $name, array $tasks = [], array $inputModalities = [], array $outputModalities = [], array $features = [], array $options = [])
    {
        parent::__construct($name, $tasks, $inputModalities, $outputModalities, $features, $options);
    }
}
