<?php
/*
 * Copyright (C) 2011-2015 Solver Ltd. All rights reserved.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at:
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations under the License.
 */
namespace Solver\Logging;

// FIXME: Implement mask.
class DelegatingStatusLog implements StatusLog {
	protected $log;
	protected $mask;
	
	public function __construct(Log $log, $mask = 15) {
		$this->log = $log;
		$this->mask = $mask;
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\StatusLog::getMask()
	 */
	public function getMask() {
		$this->mask;
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\Log::log()
	 */
	public function log(array $event, array ...$events) {
		// TODO: Implement the mask at every individual method w/o look-up for extra speed?
		static $map = [
			'error' => self::ERROR_FLAG,
			'warning' => self::WARNING_FLAG,
			'info' => self::INFO_FLAG,
			'success' => self::SUCCESS_FLAG,
		];
		
		// TODO: Optimize for one event here (common).
		if ($events) array_unshift($events, $event);
		else $events = [$event];
		
		$eventsToSend = [];
		
		// We need to be atomic, so we first check conditions, then send in one go.
		foreach ($events as $event) {
			$type = $event['type'];
			if (isset($map[$type])) {
				if ($this->mask & $map[$type]) $eventsToSend[] = $event;
			} else {
				LogException::throwUnknownType($type);
			}
		}
		
		$this->log->log(...$eventsToSend);
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\StatusLog::error()
	 */
	public function error($path = null, $message = null, $code = null, array $details = null) {
		$event = ['type' => 'error'];
		if (isset($path)) $event['path'] = $path;
		if (isset($message)) $event['message'] = $message;
		if (isset($code)) $event['code'] = $code;
		if (isset($details)) $event['details'] = $details;
		$this->log->log($event);
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\StatusLog::warning()
	 */
	public function warning($path = null, $message = null, $code = null, array $details = null) {
		$event = ['type' => 'warning'];
		if (isset($path)) $event['path'] = $path;
		if (isset($message)) $event['message'] = $message;
		if (isset($code)) $event['code'] = $code;
		if (isset($details)) $event['details'] = $details;
		$this->log->log($event);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\StatusLog::info()
	 */
	public function info($path = null, $message = null, $code = null, array $details = null) {
		$event = ['type' => 'info'];
		if (isset($path)) $event['path'] = $path;
		if (isset($message)) $event['message'] = $message;
		if (isset($code)) $event['code'] = $code;
		if (isset($details)) $event['details'] = $details;
		$this->log->log($event);
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\StatusLog::success()
	 */
	public function success($path = null, $message = null, $code = null, array $details = null) {
		$event = ['type' => 'success'];
		if (isset($path)) $event['path'] = $path;
		if (isset($message)) $event['message'] = $message;
		if (isset($code)) $event['code'] = $code;
		if (isset($details)) $event['details'] = $details;
		$this->log->log($event);
	}
}