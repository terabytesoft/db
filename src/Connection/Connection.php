<?php

declare(strict_types=1);

namespace Yiisoft\Db\Connection;

use Psr\Log\LogLevel;
use Throwable;
use Yiisoft\Cache\Dependency\Dependency;
use Yiisoft\Db\AwareTrait\LoggerAwareTrait;
use Yiisoft\Db\AwareTrait\ProfilerAwareTrait;
use Yiisoft\Db\Cache\QueryCache;
use Yiisoft\Db\Command\Command;
use Yiisoft\Db\Driver\DriverInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Query\QueryBuilder;
use Yiisoft\Db\Schema\Schema;
use Yiisoft\Db\Schema\TableSchema;
use Yiisoft\Db\Transaction\Transaction;

use function array_keys;
use function str_replace;

abstract class Connection implements ConnectionInterface
{
    use LoggerAwareTrait;
    use ProfilerAwareTrait;

    protected array $masters = [];
    protected array $slaves = [];
    protected ?ConnectionInterface $master = null;
    protected ?ConnectionInterface $slave = null;
    protected ?Transaction $transaction = null;
    private ?bool $emulatePrepare = null;
    private bool $enableSavepoint = true;
    private bool $enableSlaves = true;
    private array $quotedTableNames = [];
    private array $quotedColumnNames = [];
    private int $serverRetryInterval = 600;
    private bool $shuffleMasters = true;
    private string $tablePrefix = '';
    private QueryCache $queryCache;

    /**
     * Establishes a DB connection.
     *
     * It does nothing if a DB connection has already been established.
     *
     * @throws Exception|InvalidConfigException if connection fails
     */
    abstract public function open(): void;

    public function __construct(QueryCache $queryCache)
    {
        $this->queryCache = $queryCache;
    }

    /**
     * Close the connection before serializing.
     *
     * @return array
     */
    public function __sleep(): array
    {
        $fields = (array) $this;

        unset(
            $fields["\000" . __CLASS__ . "\000" . 'master'],
            $fields["\000" . __CLASS__ . "\000" . 'slave'],
            $fields["\000" . __CLASS__ . "\000" . 'transaction'],
            $fields["\000" . __CLASS__ . "\000" . 'schema']
        );

        return array_keys($fields);
    }

    public function areSlavesEnabled(): bool
    {
        return $this->enableSlaves;
    }

    /**
     * Starts a transaction.
     *
     * @param string|null $isolationLevel The isolation level to use for this transaction.
     *
     * {@see Transaction::begin()} for details.
     *
     * @throws Exception|InvalidConfigException|NotSupportedException|Throwable
     *
     * @return Transaction the transaction initiated
     */
    public function beginTransaction(string $isolationLevel = null): Transaction
    {
        $this->open();

        if (($transaction = $this->getTransaction()) === null) {
            $transaction = $this->transaction = new Transaction($this);
            if ($this->logger !== null) {
                $transaction->setLogger($this->logger);
            }
        }

        $transaction->begin($isolationLevel);

        return $transaction;
    }

    /**
     * Uses query cache for the queries performed with the callable.
     *
     * When query caching is enabled ({@see enableQueryCache} is true and {@see queryCache} refers to a valid cache),
     * queries performed within the callable will be cached and their results will be fetched from cache if available.
     *
     * For example,
     *
     * ```php
     * // The customer will be fetched from cache if available.
     * // If not, the query will be made against DB and cached for use next time.
     * $customer = $db->cache(function (ConnectionInterface $db) {
     *     return $db->createCommand('SELECT * FROM customer WHERE id=1')->queryOne();
     * });
     * ```
     *
     * Note that query cache is only meaningful for queries that return results. For queries performed with
     * {@see Command::execute()}, query cache will not be used.
     *
     * @param callable $callable a PHP callable that contains DB queries which will make use of query cache.
     * The signature of the callable is `function (ConnectionInterface $db)`.
     * @param int|null $duration the number of seconds that query results can remain valid in the cache. If this is not
     * set, the value of {@see queryCacheDuration} will be used instead. Use 0 to indicate that the cached data will
     * never expire.
     * @param Dependency|null $dependency the cache dependency associated with the cached query
     * results.
     *
     * @throws Throwable if there is any exception during query
     *
     * @return mixed the return result of the callable
     *
     * {@see setEnableQueryCache()}
     * {@see queryCache}
     * {@see noCache()}
     */
    public function cache(callable $callable, int $duration = null, Dependency $dependency = null)
    {
        $this->queryCache->setInfo(
            [$duration ?? $this->queryCache->getDuration(), $dependency]
        );
        $result = $callable($this);
        $this->queryCache->removeLastInfo();
        return $result;
    }

    public function close(): void
    {
        if (!empty($this->master)) {
            $this->getDriver()->PDO(null);
            $this->master->close();
            $this->master = null;
        }

        if ($this->getDriver()->getPDO() !== null) {
            $this->logger?->log(
                LogLevel::DEBUG,
                'Closing DB connection: ' . $this->getDriver()->getDsn() . ' ' . __METHOD__,
            );

            $this->getDriver()->PDO(null);
            $this->transaction = null;
        }

        if (!empty($this->slave)) {
            $this->slave->close();
            $this->slave = null;
        }
    }

    public function getEmulatePrepare(): ?bool
    {
        return $this->emulatePrepare;
    }

    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * @param string $sequenceName name of the sequence object (required by some DBMS)
     *
     * @throws Exception
     * @throws InvalidCallException
     *
     * @return string the row ID of the last row inserted, or the last value retrieved from the sequence object
     *
     * {@see http://php.net/manual/en/pdo.lastinsertid.php'>http://php.net/manual/en/pdo.lastinsertid.php}
     */
    public function getLastInsertID(string $sequenceName = ''): string
    {
        return $this->getSchema()->getLastInsertID($sequenceName);
    }

    /**
     * Returns the currently active master connection.
     *
     * If this method is called for the first time, it will try to open a master connection.
     *
     * @return Connection the currently active master connection. `null` is returned if there is no master available.
     */
    public function getMaster(): ?self
    {
        if ($this->master === null) {
            $this->master = $this->shuffleMasters
                ? $this->openFromPool($this->masters)
                : $this->openFromPoolSequentially($this->masters);
        }

        return $this->master;
    }

    /**
     * Returns the query builder for the current DB connection.
     *
     * @return QueryBuilder the query builder for the current DB connection.
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return $this->getSchema()->getQueryBuilder();
    }

    /**
     * @throws Exception
     */
    public function getServerVersion(): string
    {
        return $this->getSchema()->getServerVersion();
    }

    /**
     * Returns the currently active slave connection.
     *
     * If this method is called for the first time, it will try to open a slave connection when {@see setEnableSlaves()}
     * is true.
     *
     * @param bool $fallbackToMaster whether to return a master connection in case there is no slave connection
     * available.
     *
     * @return static the currently active slave connection. `null` is returned if there is no slave available and
     * `$fallbackToMaster` is false.
     */
    public function getSlave(bool $fallbackToMaster = true): ?self
    {
        if (!$this->enableSlaves) {
            return $fallbackToMaster ? $this : null;
        }

        if ($this->slave === null) {
            $this->slave = $this->openFromPool($this->slaves);
        }

        return $this->slave === null && $fallbackToMaster ? $this : $this->slave;
    }

    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    public function getTableSchema(string $name, $refresh = false): ?TableSchema
    {
        return $this->getSchema()->getTableSchema($name, $refresh);
    }

    /**
     * Returns the currently active transaction.
     *
     * @return Transaction|null the currently active transaction. Null if no active transaction.
     */
    public function getTransaction(): ?Transaction
    {
        return $this->transaction && $this->transaction->isActive() ? $this->transaction : null;
    }

    public function isSavepointEnabled(): bool
    {
        return $this->enableSavepoint;
    }

    /**
     * Disables query cache temporarily.
     *
     * Queries performed within the callable will not use query cache at all. For example,
     *
     * ```php
     * $db->cache(function (ConnectionInterface $db) {
     *
     *     // ... queries that use query cache ...
     *
     *     return $db->noCache(function (ConnectionInterface $db) {
     *         // this query will not use query cache
     *         return $db->createCommand('SELECT * FROM customer WHERE id=1')->queryOne();
     *     });
     * });
     * ```
     *
     * @param callable $callable a PHP callable that contains DB queries which should not use query cache. The signature
     * of the callable is `function (ConnectionInterface $db)`.
     *
     * @throws Throwable if there is any exception during query
     *
     * @return mixed the return result of the callable
     *
     * {@see enableQueryCache}
     * {@see queryCache}
     * {@see cache()}
     */
    public function noCache(callable $callable)
    {
        $queryCache = $this->queryCache;
        $queryCache->setInfo(false);
        $result = $callable($this);
        $queryCache->removeLastInfo();
        return $result;
    }

    public function quoteColumnName(string $name): string
    {
        return $this->quotedColumnNames[$name]
            ?? ($this->quotedColumnNames[$name] = $this->getSchema()->quoteColumnName($name));
    }

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
    public function quoteSql(string $sql): string
    {
        return preg_replace_callback(
            '/({{(%?[\w\-. ]+%?)}}|\\[\\[([\w\-. ]+)]])/',
            function ($matches) {
                if (isset($matches[3])) {
                    return $this->quoteColumnName($matches[3]);
                }

                return str_replace('%', $this->tablePrefix, $this->quoteTableName($matches[2]));
            },
            $sql
        );
    }

    public function quoteTableName(string $name): string
    {
        return $this->quotedTableNames[$name]
            ?? ($this->quotedTableNames[$name] = $this->getSchema()->quoteTableName($name));
    }

    public function quoteValue($value)
    {
        return $this->getSchema()->quoteValue($value);
    }

    /**
     * Whether to turn on prepare emulation. Defaults to false, meaning PDO will use the native prepare support if
     * available. For some databases (such as MySQL), this may need to be set true so that PDO can emulate to prepare
     * support to bypass the buggy native prepare support. The default value is null, which means the PDO
     * ATTR_EMULATE_PREPARES value will not be changed.
     *
     * @param bool $value
     */
    public function setEmulatePrepare(bool $value): void
    {
        $this->emulatePrepare = $value;
    }

    /**
     * Whether to enable [savepoint](http://en.wikipedia.org/wiki/Savepoint). Note that if the underlying DBMS does not
     * support savepoint, setting this property to be true will have no effect.
     *
     * @param bool $value
     */
    public function setEnableSavepoint(bool $value): void
    {
        $this->enableSavepoint = $value;
    }

    public function setEnableSlaves(bool $value): void
    {
        $this->enableSlaves = $value;
    }

    /**
     * Set connection for master server, you can specify multiple connections, adding the id for each one.
     *
     * @param string $key index master connection.
     * @param ConnectionInterface $master The connection every master.
     */
    public function setMaster(string $key, ConnectionInterface $master): void
    {
        $this->masters[$key] = $master;
    }

    /**
     * Can be used to set {@see QueryBuilder} configuration via Connection configuration array.
     *
     * @param iterable $config the {@see QueryBuilder} properties to be configured.
     */
    public function setQueryBuilder(iterable $config): void
    {
        $builder = $this->getQueryBuilder();

        foreach ($config as $key => $value) {
            $builder->{$key} = $value;
        }
    }

    /**
     * The retry interval in seconds for dead servers listed in {@see setMaster()} and {@see setSlave()}.
     *
     * @param int $value
     */
    public function setServerRetryInterval(int $value): void
    {
        $this->serverRetryInterval = $value;
    }

    /**
     * Whether to shuffle {@see setMaster()} before getting one.
     *
     * @param bool $value
     */
    public function setShuffleMasters(bool $value): void
    {
        $this->shuffleMasters = $value;
    }

    /**
     * Set connection for master slave, you can specify multiple connections, adding the id for each one.
     *
     * @param string $key index slave connection.
     * @param ConnectionInterface $slave The connection every slave.
     */
    public function setSlave(string $key, ConnectionInterface $slave): void
    {
        $this->slaves[$key] = $slave;
    }

    /**
     * The common prefix or suffix for table names. If a table name is given as `{{%TableName}}`, then the percentage
     * character `%` will be replaced with this property value. For example, `{{%post}}` becomes `{{tbl_post}}`.
     *
     * @param string $value
     */
    public function setTablePrefix(string $value): void
    {
        $this->tablePrefix = $value;
    }

    /**
     * Executes callback provided in a transaction.
     *
     * @param callable $callback a valid PHP callback that performs the job. Accepts connection instance as parameter.
     * @param string|null $isolationLevel The isolation level to use for this transaction. {@see Transaction::begin()}
     * for details.
     *
     *@throws Throwable if there is any exception during query. In this case the transaction will be rolled back.
     *
     * @return mixed result of callback function
     */
    public function transaction(callable $callback, string $isolationLevel = null)
    {
        $transaction = $this->beginTransaction($isolationLevel);

        $level = $transaction->getLevel();

        try {
            $result = $callback($this);

            if ($transaction->isActive() && $transaction->getLevel() === $level) {
                $transaction->commit();
            }
        } catch (Throwable $e) {
            $this->rollbackTransactionOnLevel($transaction, $level);

            throw $e;
        }

        return $result;
    }

    /**
     * Executes the provided callback by using the master connection.
     *
     * This method is provided so that you can temporarily force using the master connection to perform DB operations
     * even if they are read queries. For example,
     *
     * ```php
     * $result = $db->useMaster(function (ConnectionInterface $db) {
     *     return $db->createCommand('SELECT * FROM user LIMIT 1')->queryOne();
     * });
     * ```
     *
     * @param callable $callback a PHP callable to be executed by this method. Its signature is
     * `function (ConnectionInterface $db)`. Its return value will be returned by this method.
     *
     * @throws Throwable if there is any exception thrown from the callback
     *
     * @return mixed the return value of the callback
     */
    public function useMaster(callable $callback)
    {
        if ($this->enableSlaves) {
            $this->enableSlaves = false;

            try {
                $result = $callback($this);
            } catch (Throwable $e) {
                $this->enableSlaves = true;

                throw $e;
            }
            $this->enableSlaves = true;
        } else {
            $result = $callback($this);
        }

        return $result;
    }

    /**
     * Opens the connection to a server in the pool.
     *
     * This method implements the load balancing among the given list of the servers.
     *
     * Connections will be tried in random order.
     *
     * @param array $pool the list of connection configurations in the server pool
     *
     * @return Connection|null the opened DB connection, or `null` if no server is available
     */
    protected function openFromPool(array $pool): ?self
    {
        shuffle($pool);
        return $this->openFromPoolSequentially($pool);
    }

    /**
     * Opens the connection to a server in the pool.
     *
     * This method implements the load balancing among the given list of the servers.
     *
     * Connections will be tried in sequential order.
     *
     * @param array $pool
     *
     * @return ConnectionInterface|null the opened DB connection, or `null` if no server is available
     */
    protected function openFromPoolSequentially(array $pool): ?ConnectionInterface
    {
        if (!$pool) {
            return null;
        }

        foreach ($pool as $poolConnection) {
            $key = [__METHOD__, $poolConnection->getDriver()->getDsn()];

            if (
                $this->getSchema()->getSchemaCache()->isEnabled() &&
                $this->getSchema()->getSchemaCache()->getOrSet($key, null, $this->serverRetryInterval)
            ) {
                /** should not try this dead server now */
                continue;
            }

            try {
                $poolConnection->open();

                return $poolConnection;
            } catch (Exception $e) {
                if ($this->logger !== null) {
                    $this->logger->log(
                        LogLevel::WARNING,
                        "Connection ({$poolConnection->getDriver()->getDsn()}) failed: " . $e->getMessage() . ' ' . __METHOD__
                    );
                }

                if ($this->getSchema()->getSchemaCache()->isEnabled()) {
                    /** mark this server as dead and only retry it after the specified interval */
                    $this->getSchema()->getSchemaCache()->set($key, 1, $this->serverRetryInterval);
                }

                return null;
            }
        }

        return null;
    }

    /**
     * Rolls back given {@see Transaction} object if it's still active and level match. In some cases rollback can fail,
     * so this method is fail-safe. Exceptions thrown from rollback will be caught and just logged with
     * {@see logger->log()}.
     *
     * @param Transaction $transaction Transaction object given from {@see beginTransaction()}.
     * @param int $level Transaction level just after {@see beginTransaction()} call.
     */
    private function rollbackTransactionOnLevel(Transaction $transaction, int $level): void
    {
        if ($transaction->isActive() && $transaction->getLevel() === $level) {
            /**
             * {@see https://github.com/yiisoft/yii2/pull/13347}
             */
            try {
                $transaction->rollBack();
            } catch (Exception $e) {
                if ($this->logger !== null) {
                    $this->logger->log(LogLevel::ERROR, $e, [__METHOD__]);
                    /** hide this exception to be able to continue throwing original exception outside */
                }
            }
        }
    }
}
