<?php
namespace Solver\Lab;

/**
 * Use this trait to implement a controller that should handle common HTTP errors.
 * 
 * @author Stan Vass
 * @copyright © 2013-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
trait ErrorControllerTrait {
	/**
	 * @var array
	 */
	private $statusStrings = [
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		422 => 'Unprocessable Entity',
		423 => 'Locked',
		424 => 'Failed Dependency',
		426 => 'Upgrade Required',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		506 => 'Variant Also Negotiates',
		507 => 'Insufficient Storage',
		509 => 'Bandwidth Limit Exceeded',
		510 => 'Not Extended',
	];
	
	protected function getStatusCode() {
		// By convention, the route should specify the status to handle in a route variable "status".
		return $this->input->get('vars.status'); 
	}
	
	protected function getStatusString() {
		return $this->statusStrings[$this->getStatusCode()]; 
	}
	
	protected function sendStatusHeader() {
		$proto = $this->input->get('server.SERVER_PROTOCOL');
		$status = $this->getStatusCode();
		header($proto . ' ' . $status . ' ' . $this->getStatusString(), true, $status);
	}
}
