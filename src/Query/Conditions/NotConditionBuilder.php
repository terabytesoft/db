<?php

declare(strict_types=1);

namespace Yiisoft\Db\Query\Conditions;

use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Expression\ExpressionBuilderInterface;
use Yiisoft\Db\Expression\ExpressionBuilderTrait;
use Yiisoft\Db\Expression\ExpressionInterface;

/**
 * Class NotConditionBuilder builds objects of {@see NotCondition}.
 */
class NotConditionBuilder implements ExpressionBuilderInterface
{
    use ExpressionBuilderTrait;

    public function build(ExpressionInterface $expression, array &$params = []): string
    {
        $operand = $expression->getCondition();
        if ($operand === '') {
            return '';
        }

        $expession = $this->queryBuilder->buildCondition($operand, $params);

        return "{$this->getNegationOperator()} ($expession)";
    }

    protected function getNegationOperator(): string
    {
        return 'NOT';
    }
}
