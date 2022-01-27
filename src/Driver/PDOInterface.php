<?php

declare(strict_types=1);

namespace Yiisoft\Db\Driver;

use PDO;

interface PDOInterface extends DriverInterface
{
    /**
     * The PHP PDO instance associated with this DB connection. This property is mainly managed by {@see open()} and
     * {@see close()} methods. When a DB connection is active, this property will represent a PDO instance; otherwise,
     * it will be null.
     *
     * @return PDO|null
     *
     * {@see pdoClass}
     */
    public function getPdo(): ?PDO;
}
