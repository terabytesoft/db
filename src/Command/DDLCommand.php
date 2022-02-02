<?php

declare(strict_types=1);

namespace Yiisoft\Db\Command;

use Yiisoft\Db\Schema\QuoterInterface;

final class DDLCommand
{
    public function __construct(QuoterInterface $quoter)
    {
        $this->quoter = $quoter;
    }

    /**
     * Creates a SQL command for adding a check constraint to an existing table.
     *
     * @param string $name the name of the check constraint. The name will be properly quoted by the method.
     * @param string $table the table that the check constraint will be added to. The name will be properly quoted by
     * the method.
     * @param string $expression the SQL of the `CHECK` constraint.
     *
     * @return string the SQL statement for adding a check constraint to an existing table.
     */
    public function addCheck(string $name, string $table, string $expression): string
    {
        return 'ALTER TABLE ' . $this->quoter->quoteTableName($table) . ' ADD CONSTRAINT '
            . $this->quoter->quoteColumnName($name) . ' CHECK (' . $this->quoter->quoteSql($expression) . ')';
    }

    /**
     * Creates a SQL command for dropping a check constraint.
     *
     * @param string $name the name of the check constraint to be dropped. The name will be properly quoted by the
     * method.
     * @param string $table the table whose check constraint is to be dropped. The name will be properly quoted by the
     * method.
     *
     * @return string the SQL statement for dropping a check constraint.
     */
    public function dropCheck(string $name, string $table): string
    {
        return 'ALTER TABLE ' . $this->quoter->quoteTableName($table) . ' DROP CONSTRAINT '
            . $this->quoter->quoteColumnName($name);
    }
}
