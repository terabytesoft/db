<?php

declare(strict_types=1);

namespace Yiisoft\Db\Connection;

use PDO;
use Yiisoft\Db\Driver\PDODriver;

interface ConnectionPDOInterface extends ConnectionInterface
{
    /**
     * Returns the currently active driver connection.
     */
    public function getDriver(): PDODriver;

    /**
     * Returns the name of the DB driver.
     *
     * @return string name of the DB driver
     */
    public function getDriverName(): string;

    /**
     * Returns a value indicating whether the DB connection is established.
     *
     * @return bool whether the DB connection is established
     */
    public function isActive(): bool;

    /**
     * Returns the PDO instance for the currently active master connection.
     *
     * This method will open the master DB connection and then return {@see pdo}.
     *
     * @throws Exception|InvalidConfigException
     *
     * @return PDO|null the PDO instance for the currently active master connection.
     */
    public function getMasterPdo(): PDO|null;

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
}
