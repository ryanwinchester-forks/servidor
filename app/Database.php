<?php

namespace Servidor;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\MySqlSchemaManager;

class Database
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * Access the underlying database schema manager.
     *
     * @return MySqlSchemaManager
     */
    public function dbal(): MySqlSchemaManager
    {
        if (!$this->connection) {
            $this->connect();
        }

        return $this->connection->getSchemaManager();
    }

    private function connect(): Connection
    {
        if (!$this->connection) {
            $this->connection = DriverManager::getConnection([
                'user' => config('database.dbal.user'),
                'password' => config('database.dbal.password'),
                'host' => config('database.connections.mysql.host'),
                'driver' => 'pdo_mysql',
            ], new Configuration());
        }

        return $this->connection;
    }

    /**
     * Get a list of all existing databases.
     *
     * @return array
     */
    public function listDatabases(): array
    {
        return $this->dbal()->listDatabases();
    }

    /**
     * Create a database if it doesn't already exist.
     *
     * @param string $dbname
     *
     * @return bool
     */
    public function create(string $dbname): bool
    {
        if (in_array($dbname, $this->listDatabases())) {
            return true;
        }

        $this->dbal()->createDatabase($dbname);

        return in_array($dbname, $this->listDatabases());
    }
}