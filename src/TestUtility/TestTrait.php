<?php

declare(strict_types=1);

namespace Yiisoft\Db\TestUtility;

use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionObject;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Db\Cache\QueryCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Driver\DriverInterface;
use Yiisoft\Log\Logger;
use Yiisoft\Profiler\Profiler;
use Yiisoft\Profiler\ProfilerInterface;

trait TestTrait
{
    protected ?CacheInterface $cache = null;
    protected ?LoggerInterface $logger = null;
    protected ?ProfilerInterface $profiler = null;
    protected ?QueryCache $queryCache = null;
    protected ?SchemaCache $schemaCache = null;

    /**
     * Asserting two strings equality ignoring line endings.
     *
     * @param string $expected
     * @param string $actual
     * @param string $message
     */
    protected function assertEqualsWithoutLE(string $expected, string $actual, string $message = ''): void
    {
        $expected = str_replace("\r\n", "\n", $expected);
        $actual = str_replace("\r\n", "\n", $actual);

        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * Asserts that value is one of expected values.
     *
     * @param mixed $actual
     * @param array $expected
     * @param string $message
     */
    protected function assertIsOneOf($actual, array $expected, $message = ''): void
    {
        self::assertThat($actual, new IsOneOfAssert($expected), $message);
    }

    protected function createCache(): Cache
    {
        if ($this->cache === null) {
            $this->cache = new Cache(new ArrayCache());
        }
        return $this->cache;
    }

    protected function createConnection(DriverInterface $PDODriver): ConnectionInterface
    {
        $class = self::DB_CONNECTION_CLASS;
        $db = new $class($PDODriver, $this->createQueryCache(), $this->createSchemaCache());
        $db->setLogger($this->createLogger());
        $db->setProfiler($this->createProfiler());

        return $db;
    }

    protected function createLogger(): Logger
    {
        if ($this->logger === null) {
            $this->logger = new Logger();
        }
        return $this->logger;
    }

    protected function createProfiler(): Profiler
    {
        if ($this->profiler === null) {
            $this->profiler = new Profiler($this->createLogger());
        }
        return $this->profiler;
    }

    protected function createQueryCache(): QueryCache
    {
        if ($this->queryCache === null) {
            $this->queryCache = new QueryCache($this->createCache());
        }
        return $this->queryCache;
    }

    protected function createSchemaCache(): SchemaCache
    {
        if ($this->schemaCache === null) {
            $this->schemaCache = new SchemaCache($this->createCache());
        }
        return $this->schemaCache;
    }

    /**
     * @param bool $reset whether to clean up the test database.
     *
     * @return ConnectionInterface
     */
    protected function getConnection($reset = false): ConnectionInterface
    {
        if ($reset === false && isset($this->connection)) {
            return $this->connection;
        }

        if ($reset === false) {
            $pdoClass = self::DB_DRIVER_CLASS;
            $PDODriver = new $pdoClass(self::DB_DSN, self::DB_USERNAME, self::DB_PASSWORD);
            return $this->createConnection($PDODriver);
        }

        try {
            $this->prepareDatabase();
        } catch (Exception $e) {
            $this->markTestSkipped('Something wrong when preparing database: ' . $e->getMessage());
        }

        return $this->connection;
    }

    /**
     * Gets an inaccessible object property.
     *
     * @param object $object
     * @param string $propertyName
     * @param bool $revoke whether to make property inaccessible after getting.
     *
     * @return mixed
     */
    protected function getInaccessibleProperty(object $object, string $propertyName, bool $revoke = true)
    {
        $class = new ReflectionClass($object);

        while (!$class->hasProperty($propertyName)) {
            $class = $class->getParentClass();
        }

        $property = $class->getProperty($propertyName);

        $property->setAccessible(true);

        $result = $property->getValue($object);

        if ($revoke) {
            $property->setAccessible(false);
        }

        return $result;
    }

    /**
     * Invokes a inaccessible method.
     *
     * @param object $object
     * @param string $method
     * @param array $args
     * @param bool $revoke whether to make method inaccessible after execution.
     *
     * @return mixed
     */
    protected function invokeMethod(object $object, string $method, array $args = [], bool $revoke = true)
    {
        $reflection = new ReflectionObject($object);

        $method = $reflection->getMethod($method);

        $method->setAccessible(true);

        $result = $method->invokeArgs($object, $args);

        if ($revoke) {
            $method->setAccessible(false);
        }
        return $result;
    }

    protected function prepareDatabase($fixture = null): void
    {
        $fixture = $fixture ?? self::DB_FIXTURES_PATH;

        $pdoClass = self::DB_DRIVER_CLASS;
        $PDODriver = new $pdoClass(self::DB_DSN, self::DB_USERNAME, self::DB_PASSWORD);
        $this->connection = $this->createConnection($PDODriver);

        $this->connection->open();

        if (self::DB_DRIVERNAME === 'oci') {
            [$drops, $creates] = explode('/* STATEMENTS */', file_get_contents($fixture), 2);
            [$statements, $triggers, $data] = explode('/* TRIGGERS */', $creates, 3);
            $lines = array_merge(
                explode('--', $drops),
                explode(';', $statements),
                explode('/', $triggers),
                explode(';', $data)
            );
        } else {
            $lines = explode(';', file_get_contents($fixture));
        }

        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $this->connection->getDriver()->getPDO()->exec($line);
            }
        }
    }

    /**
     * Adjust dbms specific escaping.
     *
     * @param $sql
     *
     * @return mixed
     */
    protected function replaceQuotes($sql)
    {
        switch (self::DB_DRIVERNAME) {
            case 'mysql':
            case 'sqlite':
                return str_replace(['[[', ']]'], '`', $sql);
            case 'oci':
                return str_replace(['[[', ']]'], '"', $sql);
            case 'pgsql':
                // more complex replacement needed to not conflict with postgres array syntax
                return str_replace(['\\[', '\\]'], ['[', ']'], preg_replace('/(\[\[)|((?<!(\[))]])/', '"', $sql));
            case 'mssql':
                return str_replace(['[[', ']]'], ['[', ']'], $sql);
            default:
                return $sql;
        }
    }

    /**
     * Sets an inaccessible object property to a designated value.
     *
     * @param object $object
     * @param string $propertyName
     * @param $value
     * @param bool $revoke whether to make property inaccessible after setting
     */
    protected function setInaccessibleProperty(object $object, string $propertyName, $value, bool $revoke = true): void
    {
        $class = new ReflectionClass($object);

        while (!$class->hasProperty($propertyName)) {
            $class = $class->getParentClass();
        }

        $property = $class->getProperty($propertyName);

        $property->setAccessible(true);

        $property->setValue($object, $value);

        if ($revoke) {
            $property->setAccessible(false);
        }
    }
}
