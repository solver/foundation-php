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
namespace Solver\Report;

class DelegatingErrorLog implements ErrorLog {
	protected $log;
	
	public function __construct(Log $log) {
		$this->log = $log;
	}

	/* (non-PHPdoc)
	 * @see \Solver\Report\ErrorLog::error()
	 */
	public function error($path, $message, $code = null, array $details = null) {
		$this->log->log(['type' => 'error', 'path' => $path, 'message' => $message, 'code' => $code, 'details' => $details]);
	}

	/* (non-PHPdoc)
	 * @see \Solver\Report\EventLog::log()
	 */
	public function log(array $event) {
		$this->log->log($event);
	}	
}