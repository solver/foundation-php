<?php
namespace Solver\Accord;

/**
 * An interface that allows an object to be "unboxed" to a primitive value. All standard Transform and Format
 * implementations in Accord recognize this interface, and if they expect one of the primitive values, they'll unbox
 * it if given an object implementing this interface.
 * 
 * TODO: Find a better name for this interface.
 */
interface ValueBox {
	/**
	 * Returns a representation of the object that is one of the primitive value types in PHP, which are:
	 * 
	 * - null
	 * - bool
	 * - int
	 * - float
	 * - string
	 * - array
	 * 
	 * It's acceptable if arrays contain non-primitive values (you don't need to unbox everything recursively, the
	 * caller can do this if they need to.
	 * 
	 * @return null|bool|int|float|string|array
	 * A primitive value.
	 */
	function getValue();
}