<?php
namespace Solver\Sql;

/**
 * TODO: Extract standard interface from the implementation.
 */
interface SqlPool {
	/**
	 * Returns a new session.
	 * 
	 * The guarantee is no two sessions fetched from this method share the same SQL connection at the same time. The
	 * sessions internally use pooled connections to the database. When a session is explicitly closed, or it goes out
	 * of scope and is garbage collected, the connection is returned to the pool for reuse.
	 * 
	 * TODO: Explain this better.
	 * 
	 * @return SqlSession
	 */
	public function newSession();
	
	/**
	 * Returns a session with a shared connection (in the scope of this pool instance) identified by a string/int name.
	 * 
	 * Depending on the SqlPool implementation, when you call this method multiple times with the same name parameter
	 * value, you may get the same session instance, or different session instances (or mixed), but even if the session
	 * instances are different, they will share the same connection (and connection state, like opened transactions and
	 * so on) internally.
	 * 
	 * When all sessions to a given named connection are explicitly closed, unset or go out of scope, the connection is
	 * returned to the pool. Next time the same name is requested, another connection from the pool is assigned to that
	 * name (or a new one is created, if there are no available free connections in the pool).
	 * 
	 * @param string|int $name
	 * A name of the connection that returned sessions share.
	 * 
	 * The general recommendation is to pass names which specify the class name or namespace of the module that uses the
	 * shared connection (if there's always a single instance of it, relative to this pool instance). This avoids the
	 * risk of name collisions and helps developers instantly recognize the scope from the name alone, for ex.
	 * getSession(Foo\MyModule::class);
	 * 
	 * @return SqlSession
	 */
	public function getSession($name);
	
	/**
	 * Discards all connections in the pool which aren't currently in use. Any connection in use by a opened session or
	 * resultset remains valid and usable.
	 * 
	 * Normally you don't need to call this method. But it can help improve performance to flush at a point in your
	 * response when you know with some certainty that SQL connections won't be needed anymore, for ex. right before you
	 * start rendering your response template (typically all database I/O is done at this point and data is in local
	 * memory).
	 * 
	 * In those cases flushing helps the system free up connection resources faster, so other processes/threads can take
	 * advantage of them.
	 * 
	 * It can be also helpful to flush in a persistent process, after a heavy operation has upsized the pool
	 * significantly and this spike is temporary. Pool implementations may choose to automatically downsize the pool at
	 * any point, but this interface doesn't assume they do or don't. Read the documentation of your pool implementation
	 * for details about its behavior in such circumstances.
	 */
	public function flush();
}