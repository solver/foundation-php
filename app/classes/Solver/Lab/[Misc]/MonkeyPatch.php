<?php
namespace Solver\Lab;

/**
 * Utilites of last resort allowing you to monkey-patch broken third party PHP libraries, when forking the source to fix
 * them is not smart or practical.
 * 
 * @author Stan Vass
 * @copyright © 2012-2013 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class MonkeyPatch {
	/**
	 * Allows you to set a property you have no access to (including a super-class private property).
	 * 
	 * @param string $class
	 * Class context you want to access (using this you can specify exactly which class in an inheritance chain you're 
	 * targeting).
	 * 
	 * @param object $object
	 * The live instance you're setting the property on. Pass null if you're setting a static property.
	 * 
	 * @param string $name
	 * Property name.
	 * 
	 * @param mixed $value
	 * New value for the property.
	 */
	public static function set($class, $object, $name, $value) {
		$refl = new \ReflectionProperty($class, $name);
		$refl->setAccessible(true);
		
		if ($object === null) $refl->setValue($value);
		else $refl->setValue($object, $value);
		
		$refl->setAccessible(false);
	}

	/**
	 * Allows you to get a property you have no access to (including a super-class private property).
	 * 
	 * @param string $class
	 * Class context you want to access (using this you can specify exactly which class in an inheritance chain you're 
	 * targeting).
	 * 
	 * @param object $object
	 * The live instance you're getting the property value from. Pass null if you're reading a static property.
	 * 
	 * @param string $name
	 * Property name.
	 * 
	 * @return mixed
	 * Property value.
	 */
	public static function get($class, $object, $name) {
		$refl = new \ReflectionProperty($class, $name);
		$refl->setAccessible(true);
		
		if ($object === null) $value = $refl->getValue();
		else $value = $refl->getValue($object);
		
		$refl->setAccessible(false);
		return $value;
	}
		
	/**
	 * Allows you to call a method you have no access to (including a super-class private method implementation).
	 * 
	 * @param string $class
	 * Class context you want to access (using this you can specify exactly which class in an inheritance chain you're 
	 * targeting).
	 * 
	 * @param object $object
	 * The live instance you're getting the property from. Pass null if you're calling a static method.
	 * 
	 * @param string $name
	 * Property name.
	 * 
	 * @param array $args
	 * Optional. Pass a list of arguments to pass to the method.
	 * 
	 * @return mixed
	 * Return result from the method.
	 */
	public static function call($class, $object, $name, array $args = null) {
		$refl = new \ReflectionMethod($class, $name);
		$refl->setAccessible(true);
		
		if ($args) $result = $refl->invokeArgs($object, $args);
		else $result = $refl->invoke($object);
		
		$refl->setAccessible(false);
		return $result;
	}
}