<?php
namespace Solver\Shake;

/**
 * Aids in implements an idea similar to Lisp-like lazy evaluation macro. Unlike Lisp macros, a lazy expression is
 * evaluated only once and then the result is reused every time you unwrap it. So if you want your function to accept a
 * value that may optionally be a lazy-evaluated expression, you can do the following:
 * 
 * <code>
 * function output($string) {
 * 		// Only call unwrap() *if* you need the concrete value, and *immediately* before you need it, no sooner.
 * 		Lazy::unwrap($string);
 * 		echo $string;
 * }
 * 
 * output('hello world'); // Prints "hello world".
 * output(new Lazy(function () { return 'hello' . ' ' . 'world'; })); // Also prints "hello world".
 * </code>
 * 
 * FIXME: This code is experimental. Don't use in real projects. Maybe this will evolve into something like Futures as
 * done here: https://secure.phabricator.com/book/libphutil/
 */
class Lazy {
	/**
	 * @var \Closure
	 */
	protected $closure;
	
	/**
	 * @var bool
	 */
	protected $evaluated;
	
	/**
	 * @var mixed
	 */
	protected $value;
	
	public function __construct(\Closure $closure) {
		$this->closure = $closure;
	}
	
	/**
	 * Pass any value to this function. If it's a Lazy instance, it'll get replaced by the output of executing the lazy
	 * expression.
	 * 
	 * Otherwise it remains unchanged.
	 * 
	 * TODO: Not sure if we should modify this by reference or just return the value.
	 * 
	 * @param mixed $lazy
	 */
	public static function unwrap(& $lazy) {
		if ($lazy instanceof Lazy) {
			if (!$lazy->evaluated) {
				$lazy->value = $lazy->closure();
				$lazy->evaluated = true;
			}
			
			$lazy = $lazy->value;
		}
	}
}