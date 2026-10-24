<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Doctrine\EntityEmbedder;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\KeywordVectorizer;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Note;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Untracked;
use Symfony\AI\Store\Exception\InvalidArgumentException;

final class EntityEmbedderTest extends TestCase
{
    use SqliteEntityManagerTrait;

    public function testEmbedsAllEntitiesOfAClassInOneCall()
    {
        $vectorizer = new KeywordVectorizer();
        $cats = new Article('Cats');
        $dogs = new Article('Dogs', 'More dogs.');

        (new EntityEmbedder($vectorizer))->embed($this->createEntityManager(), [$cats, $dogs]);

        $this->assertSame([['Cats', "Dogs\nMore dogs."]], $vectorizer->calls);
        $this->assertSame([1.01, 0.01, 0.01], $cats->getEmbedding()?->getData());
        $this->assertSame([0.01, 2.01, 0.01], $dogs->getEmbedding()?->getData());
    }

    public function testEmbedsEachClassWithItsOwnContent()
    {
        $vectorizer = new KeywordVectorizer();

        (new EntityEmbedder($vectorizer))->embed($this->createEntityManager(), [new Article('Cats'), new Note('Fish')]);

        $this->assertSame([['Cats'], ['Note: Fish']], $vectorizer->calls);
    }

    public function testClearsTheEmbeddingWhenThereIsNothingToEmbed()
    {
        $vectorizer = new KeywordVectorizer();
        $article = new Article(' ');
        $article->setEmbedding(new Vector([1.0, 1.0, 1.0]));

        (new EntityEmbedder($vectorizer))->embed($this->createEntityManager(), [$article]);

        $this->assertNull($article->getEmbedding());
        $this->assertSame([], $vectorizer->calls);
    }

    public function testRefusesAnEntityWithoutEmbedding()
    {
        $this->expectException(InvalidArgumentException::class);

        (new EntityEmbedder(new KeywordVectorizer()))->embed($this->createEntityManager(), [new Untracked('Cats')]);
    }
}
