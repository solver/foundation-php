<?php
namespace Solver\Logging;

/**
 * A Null Object implementation compatible with Log / ErrorLog / StatusLog.
 * 
 * It does nothing and is stateless. You can pass it where a log is required when you don't need the log entries.
 * 
 * This class is a singleton, obtain an instance via NullLog::get().
 * 
 * This object intentionally doesn't implement the "Memory" interfaces, as with those you can read state, which NullLog
 * doesn't retain, and this might cause some software to misbehave. For example, if we implemented ErrorMemoryLog, we'd
 * have to return false from hasErrors(), even if the error() method has been called.
 */
class NullLog implements StatusLog {
	public static function get() {
		static $i; return $i ?: $i = new self();
	}
	
	/* (non-PHPdoc)
	 * @see \Solver\Logging\StatusLog::info()
	 */
	public function info($path, $message, $code = null, array $details = null) {}

	/* (non-PHPdoc)
	 * @see \Solver\Logging\StatusLog::success()
	 */
	public function success($path, $message, $code = null, array $details = null) {}

	/* (non-PHPdoc)
	 * @see \Solver\Logging\StatusLog::warning()
	 */
	public function warning($path, $message, $code = null, array $details = null) {}

	/* (non-PHPdoc)
	 * @see \Solver\Logging\ErrorLog::error()
	 */
	public function error($path, $message, $code = null, array $details = null) {}

	/* (non-PHPdoc)
	 * @see \Solver\Logging\Log::log()
	 */
	public function log(array $event) {}

}