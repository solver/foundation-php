<?php
namespace Solver\Web;

/**
 * This is a lightweight implementation of an HTTP response writer, so the write context can be abstracted.
 * 
 * TODO: Split into interface ResponseWriter + class NativeResponseWriter (using PHP API used here).
 * TODO: We can reduce logic here (all the intermediation with status/header/cookie properties) once we ensure we can
 * use the native API in a way 100% consistent with our semantics.
 */
class ResponseWriter {
	protected $open = false;
	protected $status = 200;
	protected $headers = [];
	protected $cookies = [];
	
	/**
	 * Sets the HTTP status code. If you don't call this method, the default status is 200.
	 * 
	 * @param int $status
	 */
	public function status($code) {
		if ($this->open) $this->throwHeadersSent();
		
		$this->status = $code;
	}
	
	/**
	 * Sets the value for a header. 
	 * 
	 * Previous headers with that name are removed from the header list (case-insensitive), unless $append is true.
	 * 
	 * @param string $name
	 * Header name. The header name MUST be given in lowercase letters only.
	 * 
	 * @param string|null $value
	 * Header value. If null: if $append is true has no affect, if false it removes any previous headers with that name.
	 * 
	 * @param bool $append
	 * Optional (default = false). If true, other headers with the same name won't be replaced, the current header will
	 * be appended after them instead. If false, other headers with the same name will be replaced by this one.
	 */
	public function header($name, $value, $append = false) {
		if ($this->open) $this->throwHeadersSent();
		
		// TODO: Make this an assertion to avoid runtime penalty?
		if ($name !== strtolower($name)) throw new \Exception('Please specify the header name in lowercase-only.');
		
		if ($append) {
			if ($value !== null) {
				if (isset($this->headers[$name])) {
					if (is_array($this->headers[$name])) {
						$this->headers[$name][] = $value;
					} else {
						$this->headers[$name] = [$this->headers[$name], $value];
					}
				}
			}
		} else {
			if ($value !== null) {
				$this->headers[$name] = $value;
			} else {
				if (isset($this->headers[$name])) unset($this->headers[$name]);
			}
		}
	}
	
	/**
	 * Sets the value & options for a cookie. 
	 * 
	 * Previous cookies with that name are overwritten (case-SENSITIVE).
	 * 
	 * @param unknown $name
	 */
	public function cookie($name) {
		if ($this->open) $this->throwHeadersSent();
		
		$this->cookies[$name] = 
	}
	
	/**
	 * Write a chunk of the message body. 
	 * 
	 * Calling this command for the first time causes the status line and headers to be written (the status and headers
	 * can't be changed after you start writing).
	 * 
	 * @param string $bytes
	 * String of bytes to write as a part of the entity.
	 */
	public function write($bytes) {
		if (!$this->open) {
			$this->sendHeaders();
			$this->open = true;
		}
		
		fwrite(STDOUT, $bytes);
	}
	
	protected function throwHeadersSent() {
		throw new \Exception('Headers were already sent.');
	}
	
	protected function sendHeaders() {
		$status = $this->status;
		$headers = $this->headers;
		$cookies = $this->cookies;
		
		if ($status !== 200) http_response_code($status);
		
		if ($headers) foreach ($headers as $name => $value) {
			if (is_array($value)) {
				foreach ($value as $i => $subValue) {
					header($name . ': ' . $subValue, $i == 0);
				}
			} else {
				header($name . ': ' . $subValue, $i == 0);
			}
		}
	}
}