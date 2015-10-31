<?php
namespace Solver\Logging;

/**
 * Reusable implementation of StatusLog's "convenience" logging methods error(), warning(), info(), success().
 * 
 * All convenience methods delegate to local method log().
 */
trait StatusLogConvenienceMethods {
	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\Log::log()
	 */
	abstract public function log(array ...$events);
	
	/*
	 * {@inheritDoc}
	 * @see \Solver\Logging\StatusLog::error()
	 */
	public function addError($path = null, $message = null, $code = null, array $details = null) {
		$event = ['type' => 'error'];
		if (isset($path)) $event['path'] = is_array($path) ? $path : explode('.', $path);
		if (isset($message)) $event['message'] = $message;
		if (isset($code)) $event['code'] = $code;
		if (isset($details)) $event['details'] = $details;
		$this->log($event);
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\StatusLog::warning()
	 */
	public function addWarning($path = null, $message = null, $code = null, array $details = null) {
		$event = ['type' => 'warning'];
		if (isset($path)) $event['path'] = is_array($path) ? $path : explode('.', $path);
		if (isset($message)) $event['message'] = $message;
		if (isset($code)) $event['code'] = $code;
		if (isset($details)) $event['details'] = $details;
		$this->log($event);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\StatusLog::info()
	 */
	public function addInfo($path = null, $message = null, $code = null, array $details = null) {
		$event = ['type' => 'info'];
		if (isset($path)) $event['path'] = is_array($path) ? $path : explode('.', $path);
		if (isset($message)) $event['message'] = $message;
		if (isset($code)) $event['code'] = $code;
		if (isset($details)) $event['details'] = $details;
		$this->log($event);
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\StatusLog::success()
	 */
	public function addSuccess($path = null, $message = null, $code = null, array $details = null) {
		$event = ['type' => 'success'];
		if (isset($path)) $event['path'] = is_array($path) ? $path : explode('.', $path);
		if (isset($message)) $event['message'] = $message;
		if (isset($code)) $event['code'] = $code;
		if (isset($details)) $event['details'] = $details;
		$this->log($event);
	}
}