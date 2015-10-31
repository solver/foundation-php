<?php
namespace Solver\Services;

use Solver\Accord\Action;

/**
 * Allows you to implement a fully dynamic endpoint, where you only define method resolve() and rely on this trait to 
 * route the resolved endpoints and actions as properties and methods, respectively, for native PHP callers. 
 * 
 * This trait also allows you to fetch an action as an object instead of invoking it, if you access it as a property
 * instead of as a method, however endpoints aren't accessible as methods, as they can't accept paramers.
 * 
 * NOTE: You can combine this trait with ResolveViaMembers to provide hybrid static/dynamic resolution, see
 * ResolveViaMembers.
 * 
 * @method resolve($name) We declare the method pro forma to avoid IDE errors when we invoke it in the trait.
 */
trait MembersViaResolve {
	public function __call($name, $args) {
		$resolution = $this->resolve($name);
		
		if ($resolution instanceof Action) {
			return $resolution->apply(...$args);
		} else {
			throw new \Exception('Cannot call undefined method "' . $name . '".');
		}
	}
	
	public function __get($name) {
		$resolution = $this->resolve($name);
		
		if ($resolution === null) {
			throw new \Exception('Cannot get undefined property "' . $name . '".');
		} else {
			return $resolution;
		}
	}
	
	public function __set($name, $value) {
		throw new \Exception('Cannot set read-only property "' . $name . '".');
	}
	
	public function __isset($name) {
		$resolution = $this->resolve($name);
		
		return $resolution !== null;
	}
	
	public function __unset($name) {
		$resolution = $this->resolve($name);
		
		if ($resolution === null) {
			throw new \Exception('Cannot unset undefined property "' . $name . '".');
		} else {
			throw new \Exception('Cannot unset read-only property "' . $name . '".');
		}
	}
}