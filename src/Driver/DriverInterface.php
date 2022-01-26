<?php

declare(strict_types=1);

namespace Yiisoft\Db\Driver;

interface DriverInterface
{
    public const DRIVER_MSSQL = 'mssql';
    public const DRIVER_MYSQL = 'mysql';
    public const DRIVER_ORACLE = 'oci';
    public const DRIVER_PGSQL = 'pgsql';
    public const DRIVER_SQLITE = 'sqlite';

    /**
     * Creates the connection instance.
     *
     * This method is called by {@see open} to establish a DB connection. The default implementation will create a php
     * connection instance. You may override this method if the default implementation is not appropriate for your DBMS.
     *
     * @return static The create coonection instance.
     */
    public function createConnectionInstance(): self;
}
