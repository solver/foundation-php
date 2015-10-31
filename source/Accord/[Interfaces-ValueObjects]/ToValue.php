<?php
namespace Solver\Accord;

/**
 * An interface that allows an object to be converted, or "unboxed" to a primitive value. All standard Transform and
 * Format implementations in Accord recognize this interface, and if they expect one of the primitive values but get an
 * object implementing this interface, they'll convert it to a value transparently and work with the value.
 * 
 * Notice that unlike FromValue::fromValue(), method toValue() doesn't accept a log. The method is not expected to fail
 * during normal operation as every value object should be, at any point, a in self-consistent state, that is
 * serializable.
 * 
 * For purely pragmatic reasons, we extend JsonSerializable, so that any FromValue object is guaranteed to be
 * JsonSerializable. Function json_encode() doesn't provide a custom hook for object conversion other than for its 
 * interface. The only options are scanning input recursively for ToValue objects (prohibitive performance-wise) or 
 * implementing JsonSerializable. You can use trait JsonSerializeViaToValue (or similar) to implement this part of the
 * interface.
 * 
 * We need ToValue as our contract is both a subset of JsonSerializable (we allow only value type output), and is  
 * semantically a superset of it (our output target is not limited to JSON serialization).
 */
interface ToValue extends \JsonSerializable {
	/**
	 * Returns a representation of the object that is one of the primitive value types in PHP, which are:
	 * 
	 * - null
	 * - scalars: bool, int, float, string
	 * - array
	 * 
	 * It's acceptable if arrays contain non-primitive values (you don't need to unbox everything recursively, the
	 * caller can do this if they need to).
	 * 
	 * @return null|bool|int|float|string|array
	 * A primitive value.
	 */
	function toValue();
}