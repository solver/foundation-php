<?php
namespace Solver\Shake;

/**
 * Creates a session-bound array under a random unique key, which can be sent to the client side to identify the piece
 * of session data behind it.
 * 
 * Essentially it does the same a web session implementation typically does, however you can have as many keys per user 
 * as you need, and not just one global key (hence having the key in your URL, for ex. means each page can have some
 * local state).
 * 
 * TODO: The class name is misleading. Figure out a better one.
 */
class LocalSession {
	protected $session;
	
	/**
	 * @param mixed $session
	 * A session bound variable where state will be stored.
	 */
	public function __construct(& $session) {
		if ($session === null) $session = [];
		$this->session = & $session;
	}
	
	/**
	 * Creates a new unique key & reserves it in the session. You can start writing and reading by binding to it.
	 * 
	 * @param string $namespace
	 * Optional (default = 'default'). A parent key under which the key will be placed. For easier debugging, it's
	 * recommended (but not required) to set the namespace to a short string that helps you identify the origin or type
	 * of data you'll store in the session-bound variable. This way, keys will be grouped by type/origin in your
	 * session, so you can find what you need more easily while debugging.
	 * 
	 * @param int $keyLength
	 * Optional (default = 16). Key length. You don't need the key to be too long, as the keys are created in the
	 * user session (not global for a server, for ex.). With the default length & alphabet, you
	 * have 62^16 = 4.76e28 combinations per namespace.
	 * 
	 * @param string $alphabet
	 * Optional (default = null). A string of characters to use for creating the key. If you pass null (or nothing),
	 * the default alphabet used will be all lowercase and uppercase latin letters plus all digits (total of 62
	 * characters). The keys produced with the default alphabet can then be included verbatim without processing and
	 * escaping in a wide range of mediums and encodings (ASCII, UTF-8, 7-bit encoding, URLs etc.).
	 * 
	 * @return string
	 * Returns a key that you can use to bind to the new session-bound variable (via method bind()).
	 */
	public function generate($namespace = 'default', $keyLength = 16, $alphabet = null) {
		if (!isset($this->session[$namespace])) {
			$this->session[$namespace] = [];
		}
		
		$ns = & $this->session[$namespace];
		
		// If you pass a dictionary of a few characters and a very short length for key, endless cycle is possible
		// here once the space is exhausted. TODO: Add some sanity checks on the namespace size.
		do $key = KeyMaker::getKey($keyLength, $alphabet); while (\key_exists($key, $ns));
		
		$ns[$key] = null;
		
		return $key;
	}
	
	/**
	 * This method works identically to Session's bind(), however instead of a name, it accepts a key and an optional
	 * namespace. Additionally, the given key/namespace combination must already exists (use generate()).
	 * 
	 * @param string $key
	 * A key generated in the same session with generate().
	 * 
	 * @param mixed $data
	 * Variable to bind to.
	 * 
	 * @param string $namespace
	 * Optional (default = 'default'). If you have used a custom namespace with generate() to obtain a key, you must
	 * pass the same namespace here.
	 * 
	 * @return bool
	 * True if the given key/namespace combination exists, false if it doesn't. If you get false, the $data variable
	 * won't be bound to a persistent session store. It remains a normal variable.
	 */
	public function bind($key, & $data, $namespace = 'default') {
		if (\key_exists($namespace, $this->session) && \key_exists($key, $this->session[$namespace])) {
			$data = $this->session[$namespace][$key];
			$this->session[$namespace][$key] = & $data;
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Deletes the session-bound variable from the session. Existing bound variables will retain their value, but will
	 * no longer be session-bound.
	 * 
	 * @param string $key
	 * A key generated in the same session with generate().
	 * 
	 * @param string $namespace
	 * Optional (default = 'default'). If you have used a custom namespace with generate() to obtain a key, you must
	 * pass the same namespace here.
	 */
	public function destroy($key, $namespace = 'default') {
		if (\key_exists($namespace, $this->session)) {
			if (\key_exists($key, $this->session[$namespace])) unset($this->session[$namespace][$key]);
			if (!$this->session[$namespace]) unset($this->session[$namespace]);
		}
	}
	
	
}