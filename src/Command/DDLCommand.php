<?php

declare(strict_types=1);

namespace Yiisoft\Db\Command;

use Yiisoft\Db\Schema\QuoterInterface;

abstract class DDLCommand
{
    public function __construct(private QuoterInterface $quoter)
    {
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
     * Builds a SQL command for adding comment to column.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the
     * method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the
     * method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     *
     * @return string the SQL statement for adding comment on column.
     */
    public function addCommentOnColumn(string $table, string $column, string $comment): string
    {
        return 'COMMENT ON COLUMN ' . $this->quoter->quoteTableName($table) . '.'
            . $this->quoter->quoteColumnName($column) . ' IS '
            . $this->quoter->quoteValue($comment);
    }

    /**
     * Builds a SQL command for adding comment to table.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the
     * method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     *
     * @return string the SQL statement for adding comment on table.
     */
    public function addCommentOnTable(string $table, string $comment): string
    {
        return 'COMMENT ON TABLE ' . $this->quoter->quoteTableName($table)
            . ' IS ' . $this->quoter->quoteValue($comment);
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

    /**
     * Builds a SQL command for adding comment to column.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the
     * method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the
     * method.
     *
     * @return string the SQL statement for adding comment on column.
     */
    public function dropCommentFromColumn(string $table, string $column): string
    {
        return 'COMMENT ON COLUMN ' . $this->quoter->quoteTableName($table) . '.'
            . $this->quoter->quoteColumnName($column) . ' IS NULL';
    }

    /**
     * Builds a SQL command for adding comment to table.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the
     * method.
     *
     * @return string the SQL statement for adding comment on column.
     */
    public function dropCommentFromTable(string $table): string
    {
        return 'COMMENT ON TABLE ' . $this->quoter->quoteTableName($table) . ' IS NULL';
    }
}
