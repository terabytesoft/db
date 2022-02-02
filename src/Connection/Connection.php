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
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Query\QueryBuilder;
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
    private int $serverRetryInterval = 600;
    private bool $shuffleMasters = true;
    private string $tablePrefix = '';

    public function __construct(private QueryCache $queryCache)
    {
    }

    public function areSlavesEnabled(): bool
    {
        return $this->enableSlaves;
    }

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

    public function cache(callable $callable, int $duration = null, Dependency $dependency = null): mixed
    {
        $this->queryCache->setInfo(
            [$duration ?? $this->queryCache->getDuration(), $dependency]
        );
        $result = $callable($this);
        $this->queryCache->removeLastInfo();
        return $result;
    }

    public function getEmulatePrepare(): ?bool
    {
        return $this->emulatePrepare;
    }

    public function getLastInsertID(string $sequenceName = ''): string
    {
        return $this->getSchema()->getLastInsertID($sequenceName);
    }

    public function getMaster(): ?self
    {
        if ($this->master === null) {
            $this->master = $this->shuffleMasters
                ? $this->openFromPool($this->masters)
                : $this->openFromPoolSequentially($this->masters);
        }

        return $this->master;
    }

    public function getServerVersion(): string
    {
        return $this->getSchema()->getServerVersion();
    }

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

    public function getTableSchema(string $name, bool $refresh = false): ?TableSchema
    {
        return $this->getSchema()->getTableSchema($name, $refresh);
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction && $this->transaction->isActive() ? $this->transaction : null;
    }

    public function isSavepointEnabled(): bool
    {
        return $this->enableSavepoint;
    }

    public function noCache(callable $callable): mixed
    {
        $queryCache = $this->queryCache;
        $queryCache->setInfo(false);
        $result = $callable($this);
        $queryCache->removeLastInfo();
        return $result;
    }

    public function setEmulatePrepare(bool $value): void
    {
        $this->emulatePrepare = $value;
    }

    public function setEnableSavepoint(bool $value): void
    {
        $this->enableSavepoint = $value;
    }

    public function setEnableSlaves(bool $value): void
    {
        $this->enableSlaves = $value;
    }

    public function setMaster(string $key, ConnectionInterface $master): void
    {
        $this->masters[$key] = $master;
    }

    public function setServerRetryInterval(int $value): void
    {
        $this->serverRetryInterval = $value;
    }

    public function setShuffleMasters(bool $value): void
    {
        $this->shuffleMasters = $value;
    }

    public function setSlave(string $key, ConnectionInterface $slave): void
    {
        $this->slaves[$key] = $slave;
    }

    public function setTablePrefix(string $value): void
    {
        $this->tablePrefix = $value;
    }

    public function transaction(callable $callback, string $isolationLevel = null): mixed
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

    public function useMaster(callable $callback): mixed
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
     * @param array $pool The list of connection configurations in the server pool.
     *
     * @return static|null The opened DB connection, or `null` if no server is available.
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
     * @return static|null The opened DB connection, or `null` if no server is available.
     */
    protected function openFromPoolSequentially(array $pool): ?self
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
                $this->logger?->log(
                    LogLevel::WARNING,
                    "Connection ({$poolConnection->getDriver()->getDsn()}) failed: " . $e->getMessage() . ' ' . __METHOD__
                );

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
