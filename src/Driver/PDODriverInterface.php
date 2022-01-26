<?php

declare(strict_types=1);

namespace Yiisoft\Db\Driver;

use PDO;

interface PDODriverInterface
{
    public const DRIVER_MSSQL = 'mssql';
    public const DRIVER_MYSQL = 'mysql';
    public const DRIVER_ORACLE = 'oci';
    public const DRIVER_PGSQL = 'pgsql';
    public const DRIVER_SQLITE = 'sqlite';

    /**
     * PDO attributes (name => value) that should be set when calling {@see open()} to establish a DB connection.
     * Please refer to the [PHP manual](http://php.net/manual/en/pdo.setattribute.php) for details about available
     * attributes.
     *
     * @param array $attributes the attributes (name => value) to be set on the DB connection.
     */
    public function attributes(array $attributes): void;

    /**
     * Creates the PDO instance.
     *
     * This method is called by {@see open} to establish a DB connection. The default implementation will create a PHP
     * PDO instance. You may override this method if the default PDO needs to be adapted for certain DBMS.
     *
     * @return PDO the pdo instance
     */
    public function createPdoInstance(): PDO;

    /**
     * The charset used for database connection. The property is only used for MySQL, PostgreSQL databases. Defaults to
     * null, meaning using default charset as configured by the database.
     *
     * For Oracle Database, the charset must be specified in the {@see dsn}, for example for UTF-8 by appending
     * `;charset=UTF-8` to the DSN string.
     *
     * The same applies for if you're using GBK or BIG5 charset with MySQL, then it's highly recommended to specify
     * charset via {@see dsn} like `'mysql:dbname=mydatabase;host=127.0.0.1;charset=GBK;'`.
     *
     * @param string|null $charset
     */
    public function charset(?string $charset): void;

    /**
     * Returns the charset currently used for database connection. The returned charset is only applicable for MySQL,
     * PostgreSQL databases.
     *
     * @return string|null the charset of the pdo instance. Null is returned if the charset is not set yet or not
     * supported by the pdo driver
     */
    public function getCharset(): ?string;

    /**
     * Return dsn string for current driver.
     */
    public function getDsn(): string;

    /**
     * Returns the password for establishing DB connection.
     *
     * @return string the password for establishing DB connection.
     */
    public function getPassword(): string;

    /**
     * Returns the PDO instance.
     *
     * @return PDO|null the PDO instance, null if the connection is not established yet.
     */
    public function getPDO(): ?PDO;

    /**
     * Returns the username for establishing DB connection.
     *
     * @return string the username for establishing DB connection.
     */
    public function getUsername(): string;

    /**
     * Set the PDO connection.
     */
    public function PDO(?PDO $pdo): void;

    /**
     * The password for establishing DB connection. Defaults to `null` meaning no password to use.
     *
     * @param string $password the password for establishing DB connection.
     */
    public function password(string $password): void;

    /**
     * The username for establishing DB connection. Defaults to `null` meaning no username to use.
     *
     * @param string $username the username for establishing DB connection.
     */
    public function username(string $username): void;
}
