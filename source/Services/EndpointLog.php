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
namespace Solver\Services;

use Solver\Logging\ErrorMemoryLog;
use Solver\Logging\LogException;

/**
 * This log is the standard method for an endpoint to report errors to its caller.
 * 
 * The log can contain one or more error, where by default when you add an error it automatically throws an
 * EndpointException (containing the log).
 * 
 * The log can throw multiple errors at once using the built-in transaction mechanism. Call begin(), add one or more
 * errors and commit(). The log supports nested transaction. When you commit the top-level transaction, and there are
 * error in the log, it will throw all errors in one go (committing won't throw if there are no errors in the log).
 * 
 * You can also rollback transactions.
 * 
 * Throwing a EndpointException with one or more errors by manipulating a EndpointLog is the standard way for a service
 * endpoint to end with an error and communicate it to its clients.
 * 
 * If a log goes out of scope with open transactions, it will throw a fatal error. This helps code which accidentally
 * leaves uncommited errors in a log.
 * 
 * TODO: Add support for reading only uncommitted & only committed errors (also support for the has* methods)?
 * Maybe as flags, to avoid method count explosion.
 */
class EndpointLog implements ErrorMemoryLog {
	/**
	 * list<int>; Lists of indexes of uncommitted events.
	 * 
	 * @var array
	 */
	protected $transactions = [];
	
	protected $errors = [];
	
	public function __destruct() {
		if ($this->transactions) throw new \Exception('An endpoint log went out of scope with one or more transactions in progress.');
	}
	
	/**
	 * FIXME: Document.
	 */
	public function begin() {
		$this->transactions[] = count($this->errors);
	}

	/**
	 * FIXME: Document.
	 */
	public function rollback() {
		if (!$this->transactions) throw new \Exception('Trying to close a transaction with no transaction open.');
		
		$start = array_pop($this->transactions);
		$end = count($this->errors);
		
		if ($end > $start)  {
			array_splice($this->errors, $start, $end - $start);
		}
	}

	/**
	 * FIXME: Document.
	 */
	public function commit() {
		if (!$this->transactions) throw new \Exception('Trying to close a transaction with no transaction open.');
		
		array_pop($this->transactions);
		
		if (!$this->transactions && $this->errors) {
			throw new EndpointException($this);
		}
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\ErrorMemoryLog::getErrors()
	 */
	public function getErrors() {
		return $this->errors;
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\ErrorMemoryLog::hasErrors()
	 */
	public function hasErrors() {
		return (bool) $this->errors;
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\MemoryLog::getEvents()
	 */
	public function getEvents($types = null) {
		if ($types === null || !$this->errors) {
			return $this->errors;
		} else {
			foreach ($types as $type) if ($type === 'error') return $this->errors;
			
			return [];
		}
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\MemoryLog::hasEvents()
	 */
	public function hasEvents($types = null) {
		return (bool) $this->getEvents($types);
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\ErrorLog::error()
	 */
	public function error($path, $message, $code = null, array $details = null) {
		$this->errors[] = [
			'type' => 'error',
			'path' => $path,
			'message' => $message,
			'code' => $code,
			'details' => $details,
		];
		
		if (!$this->transactions) throw new EndpointException($this);
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\Log::log()
	 */
	public function log(array $event) {
		if ($event['type'] !== 'error') LogException::throwUnknownType($event['type']);
		
		$this->error($event['path'], $event['message'], $event['code'], $event['details']);
	}
}