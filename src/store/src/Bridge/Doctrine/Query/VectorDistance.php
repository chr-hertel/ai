<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Query;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Literal;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Distance;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Platforms\VectorPlatformFactory;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * VECTOR_DISTANCE(e.embedding, :vector[, 'cosine'|'euclidean'|'inner_product']).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class VectorDistance extends FunctionNode
{
    public const NAME = 'VECTOR_DISTANCE';

    private Node|string $column;
    private Node|string $vector;
    private Distance $distance = Distance::Cosine;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->column = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->vector = $parser->ArithmeticPrimary();

        if ($parser->getLexer()->isNextToken(TokenType::T_COMMA)) {
            $parser->match(TokenType::T_COMMA);
            $distance = $parser->StringPrimary();

            if (!$distance instanceof Literal || null === Distance::tryFrom((string) $distance->value)) {
                throw new InvalidArgumentException(\sprintf('The distance of %s() must be one of "%s".', self::NAME, implode('", "', array_column(Distance::cases(), 'value'))));
            }

            $this->distance = Distance::from((string) $distance->value);
        }

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        $vectorPlatform = VectorPlatformFactory::create($sqlWalker->getConnection()->getDatabasePlatform());

        return $vectorPlatform->getDistanceSql(
            $sqlWalker->walkArithmeticPrimary($this->column),
            $vectorPlatform->getVectorParameterSql($sqlWalker->walkArithmeticPrimary($this->vector)),
            $this->distance,
        );
    }
}
