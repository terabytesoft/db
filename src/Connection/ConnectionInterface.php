<?php

declare(strict_types=1);

namespace Yiisoft\Db\Connection;

use PDO;
use Yiisoft\Db\Command\Command;
use yiisoft\Db\Driver\PDOInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Schema\Schema;
use Yiisoft\Db\Schema\TableSchema;

interface ConnectionInterface
{
    /**
     * Creates a command for execution.
     *
     * @param string|null $sql the SQL statement to be executed
     * @param array $params the parameters to be bound to the SQL statement
     *
     * @throws Exception
     * @throws InvalidConfigException
     *
     * @return Command the DB command
     */
    public function createCommand(?string $sql = null, array $params = []): Command;

    /**
     * Closes the currently active DB connection.
     *
     * It does nothing if the connection is already closed.
     */
    public function close(): void;

    /**
     * Returns the schema information for the database opened by this connection.
     *
     * @return Schema the schema information for the database opened by this connection.
     */
    public function getSchema(): Schema;

    /**
     * Returns a server version as a string comparable by {@see \version_compare()}.
     *
     * @return string server version as a string.
     */
    public function getServerVersion(): string;

    /**
     * Returns the PDO instance for the currently active slave connection.
     *
     * When {@see enableSlaves} is true, one of the slaves will be used for read queries, and its PDO instance will be
     * returned by this method.
     *
     * @param bool $fallbackToMaster whether to return a master PDO in case none of the slave connections is available.
     *
     * @throws Exception
     *
     * @return PDO the PDO instance for the currently active slave connection. `null` is returned if no slave connection
     * is available and `$fallbackToMaster` is false.
     */
    public function getSlavePdo(bool $fallbackToMaster = true): ?PDO;

    /**
     * Obtains the schema information for the named table.
     *
     * @param string $name table name.
     * @param bool $refresh whether to reload the table schema even if it is found in the cache.
     *
     * @return TableSchema|null
     */
    public function getTableSchema(string $name, $refresh = false): ?TableSchema;

    /**
     * Establishes a DB connection.
     *
     * It does nothing if a DB connection has already been established.
     *
     * @throws Exception|InvalidConfigException if connection fails
     */
    public function open(): void;

    /**
     * Quotes a column name for use in a query.
     *
     * If the column name contains prefix, the prefix will also be properly quoted.
     * If the column name is already quoted or contains special characters including '(', '[[' and '{{', then this
     * method will do nothing.
     *
     * @param string $name column name
     *
     * @return string the properly quoted column name
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
     * If the table name contains schema prefix, the prefix will also be properly quoted.
     * If the table name is already quoted or contains special characters including '(', '[[' and '{{', then this method
     * will do nothing.
     *
     * @param string $name table name
     *
     * @return string the properly quoted table name
     */
    public function quoteTableName(string $name): string;

    /**
     * Quotes a string value for use in a query.
     *
     * Note that if the parameter is not a string, it will be returned without change.
     *
     * @param int|string $value string to be quoted
     *
     * @throws Exception
     *
     * @return int|string the properly quoted string
     *
     * {@see http://php.net/manual/en/pdo.quote.php}
     */
    public function quoteValue($value);

    /**
     * Whether to enable read/write splitting by using {@see setSlaves()} to read data. Note that if {@see setSlaves()}
     * is empty, read/write splitting will NOT be enabled no matter what value this property takes.
     *
     * @param bool $value
     */
    public function setEnableSlaves(bool $value): void;
}
