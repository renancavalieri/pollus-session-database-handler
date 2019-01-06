<?php

/**
 * Session Database Handler
 * @license https://opensource.org/licenses/MIT MIT
 * @author Renan Cavalieri <renan@tecdicas.com>
 */

namespace Pollus\SessionDatabaseHandler;

use PDO;
use Pollus\SessionDatabaseHandler\Adapters\DatabaseAdapterInterface;
use \SessionHandlerInterface;
use \SessionIdInterface;
use \SessionUpdateTimestampHandlerInterface;
use Pollus\SessionDatabaseHandler\SessionDatabaseHandlerException;

class SessionDatabaseHandler 
implements SessionHandlerInterface, SessionIdInterface, SessionUpdateTimestampHandlerInterface
{
    /**
     * @var PDO
     */
    protected $pdo;
    
    /**
     * @var DatabaseAdapterInterface
     */
    protected $adapter;
    
    /**
     * @var string|null
     */
    protected $session_data = null;
    
    /**
     * @var bool
     */
    protected $gc_called = false;
    
    /**
     * @var bool
     */
    protected $validate_called = false;

    /**
     * @param PDO $pdo
     * @param DatabaseAdapterInterface $adapter
     */
    public function __construct(DatabaseAdapterInterface $adapter) 
    {
        $this->adapter = $adapter;
        if (function_exists("random_bytes") === false)
        {
            throw new SessionDatabaseHandlerException("random_bytes() function doesn't exists");
        }
    }
    
    /**
     * Register the handler
     */
    public function register()
    {
        session_set_save_handler
        (
            array($this, 'open'),
            array($this, 'close'),
            array($this, 'read'),
            array($this, 'write'),
            array($this, 'destroy'),
            array($this, 'gc'),
            array($this, 'create_sid'),
            array($this, 'validateId'),
            array($this, 'updateTimestamp')
        );
        session_register_shutdown('session_write_close');
    }

    /**
     * Creates an unique and cryptographically safe session ID
     * 
     * @return string
     */
    function create_sid() 
    { 
        $uniqid = uniqid();
        $length = $this->adapter->getSessionidLength() - strlen($uniqid) - 1;
        $secret = base64_encode( random_bytes( (int) (($length*3)/4) ));
        return substr($uniqid.$secret, 0, $this->adapter->getSessionidLength());
    }


    /**
     * Destroys a session
     * 
     * @param string $session_id
     * @return bool
     */
    public function destroy($session_id): bool 
    {
        return $this->adapter->delete($session_id);
    }

    /**
     * Executes the garbage collector
     * 
     * @param int $maxlifetime
     * @return bool
     */
    public function gc($maxlifetime): bool 
    {
        $this->gc_called = true;
        return true;
    }

    /**
     * Reads a session data from database
     * 
     * @param string $session_id
     * @return string
     */
    public function read($session_id): string 
    {
        if ($this->session_data === null)
        {
            if ($this->adapter->isLockingEnabled() === true && $this->adapter->inTransaction() === false)
            {
                $this->adapter->beginTransaction();
            }
            $this->session_data = $this->adapter->select($session_id, $this->getMaxLifetime());
        }
        return $this->session_data ?? "";
    }

    /**
     * Writes a session to database
     * 
     * @param string $session_id
     * @param string $session_data
     * @return bool
     */
    public function write($session_id, $session_data): bool 
    {     
        return $this->adapter->save($session_id, $session_data);
    }

    /**
     * Closes the connection and release all locks (if supported by adapter)
     * 
     * @return bool
     */
    public function close(): bool 
    {
        if ($this->adapter->isLockingEnabled())
        {
            $this->adapter->commit();
        }
        if ($this->gc_called)
        {
            $this->adapter->gc($this->getMaxLifetime());
        }
        $this->session_data = null;
        $this->validate_called = false;
        return true;
    }
    
    /**
     * This method does nothing
     * 
     * @param mixed $session_id
     * @return bool
     */
    public function validateId($session_id): bool
    {
        return true;
    }
    
    /**
     * Returns the value of session.gc_maxlifetime
     * 
     * @return int
     */
    protected function getMaxLifetime() : int
    {
        return (int) ini_get("session.gc_maxlifetime");
    }

    /**
     * This method does nothing, since this is handled by the adapter
     * 
     * @param mixed $sessionId
     * @param type $sessionData
     * @return bool
     */
    public function updateTimestamp($sessionId, $sessionData): bool
    {
        return true;
    }
    
    /**
     * This method does nothing, since this is handled by PDO
     * 
     * @return bool
     */
    public function open($save_path, $session_name): bool
    {
        return true;
    }
    
    /**
     * Validate only one time
     * 
     * @param string $session_id
     * @return bool
     */
    public function ValidateOnce(string $session_id) : bool
    {
        $this->validate_called = true;
        
        if ($this->session_data === null)
        {
            if ($this->adapter->isLockingEnabled() === true && $this->adapter->inTransaction() === false)
            {
                $this->adapter->beginTransaction();
            }
            
            $this->session_data = $this->adapter->select($session_id, $this->getMaxLifetime());
            
            if ($this->session_data === null)
            {
                return false;
            }
        }
        return true;
    }
}