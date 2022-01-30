<?php

declare(strict_types=1);

namespace Yiisoft\Db\Schema;

interface QuoterInterface
{
    /**
     * Quotes a column name for use in a query.
     *
     * If the column name contains prefix, the prefix will also be properly quoted. If the column name is already quoted
     * or contains '(', '[[' or '{{', then this method will do nothing.
     *
     * @param string $name column name.
     *
     * @return string the properly quoted column name.
     *
     * {@see quoteSimpleColumnName()}
     */
    public function quoteColumnName(string $name): string;

    /**
     * Processes a SQL statement by quoting table and column names that are enclosed within double brackets.
     *
     * Tokens enclosed within double curly brackets are treated as table names, while tokens enclosed within double
     * square brackets are column names. They will be quoted accordingly. Also, the percentage character "%" at the
     * beginning or ending of a table name will be replaced with {@see tablePrefix}.
     *
     * @param string $sql the SQL to be quoted
     *
     * @return string the quoted SQL
     */
    public function quoteSql(string $sql): string;

    /**
     * Quotes a table name for use in a query.
     *
     * If the table name contains schema prefix, the prefix will also be properly quoted. If the table name is already
     * quoted or contains '(' or '{{', then this method will do nothing.
     *
     * @param string $name table name.
     *
     * @return string the properly quoted table name.
     *
     * {@see quoteSimpleTableName()}
     */
    public function quoteTableName(string $name): string;

    /**
     * Quotes a string value for use in a query.
     *
     * Note that if the parameter is not a string, it will be returned without change.
     *
     * @param int|string $str string to be quoted.
     * @param PDO $pdo the PDO instance.
     *
     * @throws Exception
     *
     * @return int|string the properly quoted string.
     *
     * {@see http://www.php.net/manual/en/function.PDO-quote.php}
     */
    public function quoteValue(int|string $value): int|string;
}
