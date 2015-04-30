<?php
/*
 * Copyright (C) 2011-2014 Solver Ltd. All rights reserved.
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
namespace Solver\Lab;

/**
 * An exception containing one or more error events. This exception is thrown on domain & validation errors occuring in
 * a model's service layer. See ServiceEndpointLog.
 * 
 * Services may also throw other exceptions in unexpected circumstances (like database access failure).
 */
class ServiceEndpointException extends \Exception {
	/**
	 * @var \Solver\Lab\ServiceEndpointLog
	 */
	protected $log;

	/**
	 * TRICKY: As a special type of exception this one has no message/code etc. Instead it acts as a proxy for the
	 * errors contained in the log passed here.
	 * 
	 * @param ServiceEndpointLog $log
	 */
	public function __construct(ServiceEndpointLog $log) {
		parent::__construct();		
		$this->log = $log;
	}
	
	/**
	 * @return \Solver\Lab\ServiceEndpointLog
	 */
	public function getLog() {
		return $this->log;
	}
	
	public function getLogMessages() {
		$messages = [];
		
		foreach ($this->log->getErrors() as $error) {
			$message = '';
			
			if ($error['path'] !== null && $error['path'] !== '') {
				$message = '(@' . $error['path'] . ') ';
			}
			
			if ($error['message'] === null) {
				$message = 'Code: ' + $error['code'];
			} else {
				$message .= $error['message'];
			}
			
			$messages[] = $message;
		}
		
		return implode("\n", $messages);
	}
}