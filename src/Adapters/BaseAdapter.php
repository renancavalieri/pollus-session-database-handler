<?php

/**
 * Session Database Handler
 * @license https://opensource.org/licenses/MIT MIT
 * @author Renan Cavalieri <renan@tecdicas.com>
 */

namespace Pollus\SessionDatabaseHandler\Adapters;

use PDO;

/**
 * Base Adapter
 */
abstract class BaseAdapter implements DatabaseAdapterInterface
{
    /**
     * @var PDO
     */
    protected $pdo;
    
    /**
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * {@inheritDoc}
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * {@inheritDoc}
     * 
     * This implementation uses PDO's beginTransaction method.
     */
    public function beginTransaction() : bool
    {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * {@inheritDoc}
     * 
     * This implementation uses PDO's inTransaction method.
     */
    public function inTransaction() : bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * {@inheritDoc}
     * 
     * This implementation uses PDO's commit method.
     */
    public function commit() : bool
    {
        return $this->pdo->commit();
    }
}