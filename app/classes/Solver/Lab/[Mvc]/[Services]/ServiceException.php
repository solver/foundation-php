<?php
namespace Solver\Lab;

/**
 * An exception containing one or more error events. This exception is thrown on domain & validation errors occuring in
 * a model's service layer. See ServiceLog.
 * 
 * Services may also throw other exceptions in unexpected circumstances (like database access failure). 
 * 
 * TODO: Override getMessage() to print all error messages (with codes and paths if any).
 * 
 * @author Stan Vass
 * @copyright © 2013-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class ServiceException extends \Exception implements EventProvider {
	/**
	 * @var \Solver\Lab\ServiceLog
	 */
	protected $log;
	
	/**
	 * TRICKY: As a special type of exception this one has no message/code etc. Instead it acts as a proxy for the
	 * errors contained in the log passed here.
	 * 
	 * @param ServiceLog $log
	 */
	public function __construct(ServiceLog $log) {
		parent::__construct();		
		$this->log = $log;
	}
		
	public function getAllEvents() {
		return $this->log->getAllEvents();
	}
}