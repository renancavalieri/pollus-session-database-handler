<?php

/**
 * Session Database Handler
 * @license https://opensource.org/licenses/MIT MIT
 * @author Renan Cavalieri <renan@tecdicas.com>
 */

namespace Pollus\SessionDatabaseHandler\Adapters;

use PDO;

use Pollus\SessionDatabaseHandler\SessionAdapterException;

/**
 * MySQL Adapter
 * 
 * This class implements pessimistic locking and MySQL DBMS support.
 * 
 * The locking could be disabled to avoid deadlocks, however race condition 
 * could occur.
 */
class MySQLAdapter extends BaseAdapter implements DatabaseAdapterInterface
{
    /**
     * Locking Enabled
     * 
     * @var bool
     */
    protected $lockEnabled;
    
    /**
     * Session ID Length
     * 
     * @var int
     */
    protected $session_id_length = 256;
    
    /**
     * Table name
     * 
     * @var string
     */
    protected $table = "sessions";
    
    /**
     * This variable stores how many time this adapter hits the database
     * 
     * @var int
     */
    public $hitCounter = 0;
    
    /**
     * @param PDO $pdo
     * @param bool $lockEnabled - When TRUE, enables the row locking
     * @param int $session_id_length
     */
    public function __construct(PDO $pdo, bool $lockEnabled = true, int $session_id_length = 256, string $table = "sessions")
    {
        parent::__construct($pdo);
        $this->lockEnabled = $lockEnabled;
        
        if ($session_id_length < 256)
        {
            throw new SessionAdapterException("The value of session ID length cannot be less than 256");
        }
        
        if (strlen($table) === 0)
        {
            throw new SessionAdapterException("Table name cannot be empty");
        }
        
        $this->table = $table;
        $this->session_id_length = $session_id_length;
    }
    
    /**
     * Deletes a session
     * 
     * @param string $session_id
     * @return bool
     */
    public function delete(string $session_id): bool
    {
        $this->hitCounter++;
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindValue("id", $session_id, PDO::PARAM_STR);
        return $stmt->execute();
    }

    /**
     * Deletes all expired sessions
     * 
     * @param int $maxlifetime
     * @return bool
     */
    public function gc(int $maxlifetime): bool
    {
        $this->hitCounter++;
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE last_activity <= :last_activity");
        $stmt->bindValue("last_activity", $this->subSecDate($maxlifetime), PDO::PARAM_STR);
        return $stmt->execute();
    }

    /**
     * Saves a session to database using "REPLACE INTO"
     * 
     * @param string $session_id
     * @param string $session_data
     * @return bool
     */
    public function save(string $session_id, string $session_data): bool
    {
        $this->hitCounter++;
        $stmt = $this->pdo->prepare("REPLACE INTO {$this->table} (id, session_data, last_activity)
                                     VALUES (:session_id, :session_data, :last_activity)");
        
        $stmt->bindValue("session_id", $session_id, PDO::PARAM_STR);
        $stmt->bindValue("session_data", $session_data, PDO::PARAM_STR);
        $stmt->bindValue("last_activity", date('Y-m-d H:i:s'), PDO::PARAM_STR);
        return $stmt->execute();
    }

    /**
     * Selects a session by its given ID and returns the session data. NULL
     * will be returned if the selected session doesn't exists or is expired.
     * 
     * If $lockEnabled is TRUE, a row lock will be performed.
     * 
     * @param string $session_id
     * @param int $maxlifetime
     * @return string|null
     */
    public function select(string $session_id, int $maxlifetime): ?string
    {
        $this->hitCounter++;
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table}
                WHERE id = :session_id
                AND last_activity > :last_activity"
                . ($this->lockEnabled ? ' FOR UPDATE' : ''));
        $stmt->bindValue("session_id", $session_id, PDO::PARAM_STR);
        $stmt->bindValue("last_activity", $this->subSecDate($maxlifetime), PDO::PARAM_STR);
        
        if ($stmt->execute())
        {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (($result["id"] ?? null) !== null)
            {
                return $result["session_data"];
            }
        }
        
        return null;
    }

    /**
     * Returns TRUE if locking is enabled
     * 
     * @return bool
     */
    public function isLockingEnabled(): bool
    {
        return $this->lockEnabled;
    }

    /**
     * Returns the session ID length
     * 
     * @return int
     */
    public function getSessionidLength(): int
    {
        return $this->session_id_length;
    }
    
    /**
     * Subtract seconds from a given date
     * 
     * @param int $seconds
     * @return string
     */
    protected function subSecDate(int $seconds) : string
    {
        return date("Y-m-d H:i:s", strtotime(date('Y-m-d H:i:s')) - $seconds);
    }

}
