<?php
namespace Solver\Services;

/**
 * Implements resolve() by reading the statically declared public properties and methods of your class (it skips over
 * constructors, destructors and other magic methods by ignoring any method that starts with a double underscore).
 * 
 * NOTE: You can combine this trait with DynamicEndpoint to provide hybrid static/dynamic resolution. Alias this trait's
 * resolve() as a protected method under another name, for ex. resolveStatic(), then implement your own resolve(), which
 * delegates to resolveStatic() and falls back to dynamic resolution on a miss (or vice versa).
 */
trait StaticEndpoint {
	/**
	 * Resolves public properties (including getters created via \Solver\Lab\Properties::defineProperty) that are
	 * endpoints, and any public methods except magic methods (leading double underscore).
	 * 
	 * @param string $name
	 * @return \solver\Lab\Endpoint|\Closure|null
	 */
	public function resolve($name) {
		/*
		 * Attempt to resolve to a public method.
		 */
		
		if (substr($name, 0, 2) === '__') goto noMethod;
		
		$classRefl = new \ReflectionClass($this);
		if (!$classRefl->hasMethod($name)) goto noMethod;
		
		$methodRefl = $classRefl->getMethod($name);
		if (!$methodRefl->isPublic() || $methodRefl->isStatic() || $methodRefl->isAbstract()) goto noMethod;
		
		return $methodRefl->getClosure($this);
		
		noMethod:
		
		/*
		 * Attempt to resolve as a getter property from trait Solver\Lab\Properties.
		 */
		
		if (!isset($this->Solver_Lab_Properties_map[$name]['get'])) goto noGetter;
		
		$property = $this->Solver_Lab_Properties_map[$name]['get']->__invoke();
		if (!$property instanceof Endpoint) goto noGetter;
		
		return $property;
		
		noGetter:
		
		/*
		 * Attempt to resolve as a regular public property.
		 */
		
		$classRefl = new \ReflectionClass($this);
		if (!$classRefl->hasProperty($name)) goto noProperty;
		
		$propertyRefl = $classRefl->getProperty($name);
		if (!$propertyRefl->isPublic() || !$propertyRefl->isStatic()) goto noProperty;
		
		$property = $this->{$name};
		if (!$property instanceof Endpoint) goto noProperty;
		
		return $property;
		
		noProperty:
		
		/*
		 * We're out of resolutions.
		 */
		
		return null;
	}
}