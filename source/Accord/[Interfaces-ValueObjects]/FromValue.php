<?php
namespace Solver\Accord;

use Solver\Logging\StatusLog;

/**
 * An interface that allows an object to be created from a primitive value (i.e. it can "box" the value). This interface
 * is an approach for creating objects which encapsulate a given value type, and validate themselves (Value Objects).
 * 
 * See ObjectFromValueFormat.
 */
interface FromValue {
	/**
	 * Takes a value and returns a new instance of the class (or null on error), which wraps or semantically represents
	 * this value.
	 * 
	 * The semantics of this method are identical to the apply() method for Transform instances.
	 * 
	 * @param null|bool|int|float|string|array $input
	 * One of the primitive value types in PHP, which are:
	 * 
	 * - null
	 * - scalars: bool, int, float, string
	 * - array
	 * 
	 * It's acceptable if arrays contain non-primitive values. This method should convert nested values if they need to.
	 * 
	 * @param \Solver\Logging\StatusLog $log
	 * The method will log here events that have occured during the conversion.
	 *  
	 * @return self
	 * An instance of the class implementing this interface.
	 * 
	 * @throws ActionException
	 * If the conversion fails (for ex. due to invalid input).
	 */
	static function fromValue($input, StatusLog $log = null);
}