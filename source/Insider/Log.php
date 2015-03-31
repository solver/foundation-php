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
namespace Solver\Insider;

/**
 * DO NOT instantiate this directly. Use Insider::getLog().
 */
class Log {
	protected $callback, $eventBase;
	
	
	public function __construct($path, $code, $callback) {
		$this->eventBase = [];
		$this->eventBase['path'] = $path;
		$this->eventBase['code'] = $code;
		$this->callback = $callback;
	}

	public function addError($message) 			{ return $this->addSimple('info', $message); }
	public function addWarning($message) 		{ return $this->addSimple('warning', $message); }
	public function addSuccess($message) 		{ return $this->addSimple('success', $message); }
	public function addInfo($message) 			{ return $this->addSimple('info', $message); }
	public function addDeprecated($message) 	{ return $this->addSimple('deprecated', $message); }
	public function addExperimental($message) 	{ return $this->addSimple('experimental', $message); }
	
	public function addException($exception) {
		return new LogExtension([
			'type' => 'exception',
			'e.message' => $exception->getMessage(),
			'e.code' => $exception->getCode(), 
			'e.file' => $exception->getFile(),
			'e.line' => $exception->getLine(),
			'e.trace' => $exception->getTraceAsString(),
			'exception' => $exception,
		], $this->callback);
	}
	public function addVar($var) { 
		return new LogExtensionForVar([
				'type' => 'var', 
				'var' => $var,
		] + $this->eventBase, $this->callback);
	}
	
// 	public function addSqlQuery($sql) {
// 		return new LogExtensionForSql(['type' => 'sql.query', 'sql' => $sql], $this->callback);
// 	}
	
// 	public function addSqlCommand($sql) {
// 		return new LogExtensionForSql(['type' => 'sql.command', 'sql' => $sql], $this->callback);
// 	}
	
// 	public function addSqlFetch($sql) {
// 		return new LogExtensionForSql(['type' => 'sql.fetch', 'sql' => $sql], $this->callback);
// 	}

	protected function addSimple($type, $message) {
		return new LogExtension([
			'type' => $type, 
			'message' => $message,
		] + $this->eventBase, $this->callback);
	}
}

class LogExtension {
	protected $event, $callback;
	
	public function __construct($event, $callback) {
		$this->event = $event;
		$this->callback = $callback;
	}
	
	public function withMeter($meter) {
		$this->event['meter'] = $meter;
		return $this;
	}
	
	public function withDetails($details) {
		$this->event['details'] = $details;
		return $this;
	}
	
	public function __destruct() {
		$this->callback->__invoke($this->event);
	}
}

class LogExtensionForVar extends LogExtension {	
	public function withMessage($message) {
		$this->event['message'] = $message;
		return $this;
	}
}

// class LogExtensionForSql extends LogExtensionWithMessage {	
// 	public function withExplain($explain) {
// 		$this->event['explain'] = $explain;
// 		return $this;
// 	}
// }