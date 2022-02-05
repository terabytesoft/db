<?php

declare(strict_types=1);

namespace Yiisoft\Db\Command;

use Exception;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Schema\QuoterInterface;

abstract class DMLCommand
{
    public function __construct(private QuoterInterface $quoter)
    {
    }

    /**
     * Creates a SQL statement for resetting the sequence value of a table's primary key.
     *
     * The sequence will be reset such that the primary key of the next new row inserted will have the specified value
     * or 1.
     *
     * @param string $tableName the name of the table whose primary key sequence will be reset.
     * @param array|int|string|null $value the value for the primary key of the next new row inserted. If this is not
     * set, the next new row's primary key will have a value 1.
     *
     * @throws Exception|NotSupportedException if this is not supported by the underlying DBMS.
     *
     * @return string the SQL statement for resetting sequence.
     */
    public function resetSequence(string $tableName, array|int|string|null $value = null): string
    {
        throw new NotSupportedException(static::class . ' does not support resetting sequence.');
    }
}
