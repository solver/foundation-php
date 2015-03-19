<?php
namespace Solver\Lab;

/**
 * Provides a generic implementation of needed facilities for a service endpoint (see \Solver\Lab\ServiceEndpoint).
 */
trait SimpleServiceEndpoint {
	use Properties;
	
	/**
	 * Resolves public properties (including getter ones created via defineEndpointProperty or defineProperty) that are
	 * endpoints, and any public methods except magic methods (leading double underscore).
	 * 
	 * @param string $name
	 * @return \solver\Lab\ServiceEndpoint|\Closure|null
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
		
		if (!isset($this->solverLabProperties[$name]['get'])) goto noGetter;
		
		$property = $this->solverLabProperties[$name]['get']();
		if (!$property instanceof ServiceEndpoint) goto noGetter;
		
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
		if (!$property instanceof ServiceEndpoint) goto noProperty;
		
		return $property;
		
		noProperty:
		
		/*
		 * We're out of resolutions.
		 */
		
		return null;
	}
	
	/**
	 * Creates a read-only property that's instantiated lazily on first access via the passed $factory closure.
	 * 
	 * In order to expose this property in IDEs, create it as an unititialized public property in your class, this
	 * method will unset it, so the getter gets triggered instead.
	 * 
	 * @param string $name
	 * Name for this property.
	 * 
	 * @param \Closure $factory
	 * A closure that must return a ServiceEndpoint instance (the factory is invoked no more than once, no need to cache
	 * the instance inside the factory, this is taken care of for you).
	 */
	protected function defineEndpointProperty($name, \Closure $factory) {
		if (property_exists($this, $name)) {
			unset($this->{$name});
		}
		
		$instance = null;
			
		$this->defineProperty($name, function () use (& $instance, $factory) {
			if ($instance === null) $instance = $factory();
			return $instance;
		}, false);
	}
}