<?php

declare(strict_types=1);

namespace Yiisoft\Db\Driver;

interface DriverInterface
{
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
