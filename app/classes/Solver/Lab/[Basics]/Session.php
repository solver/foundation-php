<?php
namespace Solver\Lab;

/**
 * Basic session API.
 * 
 * @author Stan Vass
 * @copyright © 2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class Session {
	private $started = false;
	
	/**
	 * Starts the session.
	 * 
	 * You don't need to call this manually, it's invoked on demand as you call a method needing a started session.
	 */
	public function start() {
		if ($this->started) throw new \Exception('The session is already started.');
		
		if (\session_id() === '') {
			\session_start();			
			$this->started = true;
		} else {
			throw new \Exception('Someone started the session elsewhere by directly invoking the native API.');
		}
	}
	
	/**
	 * Binding creates persistent variables anywhere you need them, without having to directly write and read from
	 * $_SESSION.
	 * 
	 * <code>
	 * $count = 0; // Initialization value for those with a new session (the default is null otherwise).
	 * $session = new Session();
	 * $session->bind('count', $count);
	 * $count++;
	 * echo $count; // Will output 1, 2, 3, 4 as you refresh the page.
	 * </code>
	 * 
	 * IMPORTANT: If you need to pass a session-bound variable, always pass by reference (i.e. "&"), otherwise you're
	 * passing only the current value (i.e. so then changing the value on the other side won't update the session). One
	 * exception to this rule is if the value in your variable is an object (ex. stdClass), which is always passed by 
	 * reference.
	 * 
	 * @param string $name
	 * A unique name used to store the data in the session. It can be convenient to use __CLASS__ and __METHOD__ (for
	 * factory methods) here as the name base in order to provide unique, intuitive names when you have to look at your
	 * session data and debug. Additional suffixes/prefixes (such as unique object instance identifiers) can be added to
	 * make the name unique as you need.
	 * 
	 * @param mixed $data
	 * The initial value of $data will be used to initialize the session store, if it doesn't already exist, otherwise
	 * the value in the session store will overwrite $data's value. After binding, any changes to the value of $data
	 * will be persisted in the session, without further method calls to this class (just re-bind on the next request).
	 */
	public function bind($name, & $data) {
		if (!$this->started) $this->start();		
		if (\key_exists($name, $_SESSION)) $data = $_SESSION[$name];
		$_SESSION[$name] = & $data;
	}
}