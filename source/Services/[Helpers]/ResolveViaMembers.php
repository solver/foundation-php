<?php
namespace Solver\Services;

use Solver\Accord\AnonAction;
use Solver\Accord\Action;
/**
 * Implements resolve() by reading the declared public properties and methods of your class (it skips over
 * constructors, destructors and other magic methods by ignoring any method that starts with a double underscore).
 * 
 * Endpoints must be a property, and actions can be a method, a property an instance of an Action, or an instance of a
 * Closure (automatically converted to an Action).
 * 
 * NOTE: You can combine this trait with MembersViaResolve to provide hybrid static/dynamic resolution. They're not 
 * mutually exclusive. You can either set property $resolveFilter, or override resolve() and delegate to the 
 * implementation here only when you don't have another resolution.
 *  
 * @property array $__handlers Declared for IDEs.
 */
trait ResolveViaMembers {
	/**
	 * An optional filter you can set in your constructor. Signature:
	 * 
	 * (resolution: mixed, name: string = null) => Endpoint|Action|Closure|null
	 *  
	 * It'll receive the unfiltered resolution, before it's type-checked (so methods will be closures, not Action
	 * instances, etc.) and you can override it. Note that your filter will be called even if the resolution is null.
	 * This allows you to have a "wildcard" resolver for properties not on your object.
	 * 
	 * If you return a Closure instance, it'll be converted to a generic action. If you convert closures to actions
	 * yourself, this gives you the chance to select a custom Action wrapper for your endpoint methods, that may provide
	 * you with a customized method signature, transformed input and caller log etc.
	 * 
	 * @var \Closure|null
	 */
	protected $resolveFilter = null;
	
	/**
	 * Resolves public properties (including those defined for \Solver\Base\Magic) that are endpoints, and any
	 * public methods except magic methods (leading double underscore).
	 * 
	 * @param string $name
	 * @return \solver\Lab\Endpoint|\Closure|null
	 */
	public function resolve($name) {
		$filter = $this->resolveFilter;
		
		/*
		 * Attempt to resolve from \Solver\Base\Magic handlers.
		 */
		
		if (isset($this->__handlers)) {
			$handlers = isset($this->__handlers[$name]) ? $this->__handlers[$name] : null;	
			if (isset($handlers['call'])) { $resolution = $handlers['call']; goto resolved; }
			if (isset($handlers['readonly'])) { $resolution = $handlers['readonly']; goto resolved; }
			if (isset($handlers['get'])) { $resolution = $handlers['get'](); goto resolved; }
		}
		
		/*
		 * Attempt to resolve to a public method (except magic double underscore methods).
		 */
		
		if (substr($name, 0, 2) === '__') goto methodNotFound;
		
		$classRefl = new \ReflectionClass($this);
		if (!$classRefl->hasMethod($name)) goto methodNotFound;
		
		$methodRefl = $classRefl->getMethod($name);
		if (!$methodRefl->isPublic() || $methodRefl->isStatic() || $methodRefl->isAbstract()) goto methodNotFound;
		
		$resolution = $methodRefl->getClosure($this);
		goto resolved;
		
		methodNotFound:
		
		/*
		 * Attempt to resolve as a regular public property.
		 */
		
		// TODO: property_exists + new ReflectionProperty() faster? Same for method resolution above.
		$classRefl = new \ReflectionClass($this);
		if (!$classRefl->hasProperty($name)) { $resolution = null; goto resolved; }
		
		$propertyRefl = $classRefl->getProperty($name);
		if (!$propertyRefl->isPublic() || !$propertyRefl->isStatic()) { $resolution = null; goto resolved; }
		
		$resolution = $this->{$name};
		goto resolved;
		
		/*
		 * We have our pick.
		 */
		resolved:
				
		if ($filter) $resolution = $filter($resolution, $name);
		
		if ($resolution === null) return null;		
		if ($resolution instanceof \Closure) return new AnonAction($resolution);
		if ($resolution instanceof Endpoint || $resolution instanceof Action) return $resolution;
		
		// Developer mistake: don't expose public methods and properties that aren't usable for resolution. If you do, 
		// supply $this->resolveFilter to filter the invalid ones out by returning null.
		throw new \Exception('Name "' . $name . '" did not resolve to a supported type (Endpoint, Action, Closure, null).');
	}
}