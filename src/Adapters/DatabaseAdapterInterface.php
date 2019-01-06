<?php

/**
 * Session Database Handler
 * @license https://opensource.org/licenses/MIT MIT
 * @author Renan Cavalieri <renan@tecdicas.com>
 */

namespace Pollus\SessionDatabaseHandler\Adapters;

use PDO;

/**
 * Database Adapter Interface 
 */
interface DatabaseAdapterInterface
{
    /**
     * This method should delete a session and return TRUE on success
     * 
     * @param string $session_id
     * @return bool
     */
    public function delete(string $session_id) : bool;
    
    /**
     * This method should return the session data as string.
     * 
     * All implementatios that supports row locking should lock the selected 
     * row on this method.
     * 
     * When a session doesn't exist or is expired, NULL should be returned
     * 
     * @param string $session_id
     * @param int $maxlifetime - Specifies the number of seconds after which 
     * a session will be seen as expired
     * 
     * @return string|null
     */
    public function select(string $session_id, int $maxlifetime) : ?string;
    
    /**
     * This method should insert or update a session on database, containing 
     * its ID, session data and current timestamp.
     * 
     * This method shouldn't generate the session ID
     * 
     * @param string $session_id
     * @param string $session_data
     * @return bool
     */
    public function save(string $session_id, string $session_data) : bool;
    
    /**
     * This method should delete all expired sessions
     * 
     * @param int $maxlifetime - Specifies the number of seconds after which 
     * a session will be seen as expired and potentially cleaned up.
     * 
     * @return bool
     */
    public function gc(int $maxlifetime) : bool;
    
    /**
     * Returns the PDO object
     * 
     * @return PDO
     */
    public function getPdo() : PDO;
    
    /**
     * Starts a transaction (if supported)
     * 
     * @return bool
     */
    public function beginTransaction() : bool;
    
    /**
     * Commits a transaction and returns TRUE on success
     * 
     * @return bool
     */
    public function commit() : bool;
    
    /**
     * Returns TRUE when row locking is supported and is enabled
     * 
     * @return bool
     */
    public function isLockingEnabled() : bool;
    
    /**
     * Returns TRUE when a transaction is supported and active
     * 
     * @return bool
     */
    public function inTransaction() : bool;
    
    /**
     * Returns the session ID length.
     * 
     * @return int
     */
    public function getSessionidLength() : int;
}
