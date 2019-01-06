<?php

/**
 * Session Database Handler
 * @license https://opensource.org/licenses/MIT MIT
 * @author Renan Cavalieri <renan@tecdicas.com>
 */

namespace Pollus\SessionDatabaseHandler;

use Pollus\SessionDatabaseHandler\SessionDatabaseHandler;

/**
 * This class is based on slim-session from bryanjhv
 * 
 * @see https://github.com/bryanjhv/slim-session
 */
class SessionManager implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     * @var array
     */
    protected $settings;
    
    /**
     * @var SessionDatabaseHandler
     */
    protected $handler;
    
    /**
     * @param string $name
     * @param SessionDatabaseHandler $handler
     * @param array $settings
     */
    public function __construct(SessionDatabaseHandler $handler, array $settings = [])
    {
        $defaults = 
        [
            'lifetime'     => 60 * 30,
            'path'         => '/',
            'domain'       => null,
            'secure'       => false,
            'httponly'     => true,
            'name'         => "PHPSESSION",
            'autorefresh'  => true,
            'ini_settings' => [],
        ];
        
        $settings = array_merge($defaults, $settings);
        $this->settings = $settings;
        $this->handler = $handler;
        $this->iniSet($settings['ini_settings']);
        $this->handler->register();
    }
    
    /**
     * Starts a session
     * 
     * @return void
     */
    public function start() : void
    {
        $inactive = session_status() === PHP_SESSION_NONE;
        if (!$inactive) return;
        $settings = $this->settings;
        $name = $settings['name'];
        
        session_set_cookie_params
        (
            $settings['lifetime'],
            $settings['path'],
            $settings['domain'],
            $settings['secure'],
            $settings['httponly']
        );
        
        ini_set('session.gc_maxlifetime', $settings["lifetime"]);
        
        // Refresh session cookie when "inactive",
        // else PHP won't know we want this to refresh
        if ($settings['autorefresh'] && isset($_COOKIE[$name])) {
            setcookie(
                $name,
                $_COOKIE[$name],
                time() + $settings['lifetime'],
                $settings['path'],
                $settings['domain'],
                $settings['secure'],
                $settings['httponly']
            );
        }
        session_name($name);
        session_cache_limiter(false);
        session_start();
        
        if($this->handler->ValidateOnce(session_id()) === false)
        {
            $this->regenerate(true);
        }
    }
    
    /**
     * Get a session variable.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->exists($key)
            ? $_SESSION[$key]
            : $default;
    }
    
    /**
     * Set a session variable.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
        return $this;
    }
    
    /**
     * Merge values recursively.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function merge($key, $value)
    {
        if (is_array($value) && is_array($old = $this->get($key))) {
            $value = array_merge_recursive($old, $value);
        }
        return $this->set($key, $value);
    }
    
    /**
     * Delete a session variable.
     *
     * @param string $key
     *
     * @return $this
     */
    public function delete($key)
    {
        if ($this->exists($key)) {
            unset($_SESSION[$key]);
        }
        return $this;
    }
    
    /**
     * Clear all session variables.
     *
     * @return $this
     */
    
    public function clear()
    {
        $_SESSION = [];
        return $this;
    }
    
    /**
     * Check if a session variable is set.
     *
     * @param string $key
     *
     * @return bool
     */
    public function exists($key)
    {
        return array_key_exists($key, $_SESSION);
    }
    
    /**
     * Get or set the session ID
     *
     * @param string $id
     *
     * @return string
     */
    public function id(?string $id = null)
    {
        return session_id($id);
    }
    
    /**
     * Regenerate session ID
     * 
     * @param bool $delete_old_session
     * @return bool
     */
    public function regenerate(bool $delete_old_session = false) : bool
    {
        $this->invalidateCookie();
        return session_regenerate_id($delete_old_session);
    }
    
    /**
     * Destroy the session.
     */
    public function destroy()
    {
        session_unset();
        session_destroy();
        session_write_close();
        $this->invalidateCookie();
    }
    
    /**
     * Magic method for get.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }
    
    /**
     * Magic method for set.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function __set($key, $value)
    {
        $this->set($key, $value);
    }
    
    /**
     * Magic method for delete.
     *
     * @param string $key
     */
    public function __unset($key)
    {
        $this->delete($key);
    }
    
    /**
     * Magic method for exists.
     *
     * @param string $key
     *
     * @return bool
     */
    public function __isset($key)
    {
        return $this->exists($key);
    }
    
    /**
     * Count elements of an object.
     *
     * @return int
     */
    public function count()
    {
        return count($_SESSION);
    }
    
    /**
     * Retrieve an external Iterator.
     *
     * @return \Traversable
     */
    public function getIterator()
    {
        return new \ArrayIterator($_SESSION);
    }
    
    /**
     * Whether an array offset exists.
     *
     * @param mixed $offset
     *
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return $this->exists($offset);
    }
    
    /**
     * Retrieve value by offset.
     *
     * @param mixed $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }
    
    /**
     * Set a value by offset.
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }
    
    /**
     * Remove a value by offset.
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->delete($offset);
    }
    
    /**
     * Sets INI settings
     * 
     * @param array $settings
     */
    protected function iniSet(array $settings)
    {
        foreach ($settings as $key => $val) 
        {
            if (strpos($key, 'session.') === 0) 
            {
                ini_set($key, $val);
            }
        }
    }
    
    /**
     * Invalidates the cookie
     */
    protected function invalidateCookie()
    {
        if (ini_get('session.use_cookies')) 
        {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 4200,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
    }
}
