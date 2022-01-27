<?php

declare(strict_types=1);

namespace Yiisoft\Db\Connection;

use Yiisoft\Db\Driver\PDOInterface;

interface ConnectionPDOInterface extends ConnectionInterface
{
    /**
     * Returns the currently active driver connection.
     */
    public function getDriver(): PDOInterface;
}
