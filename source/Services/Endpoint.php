<?php
namespace Solver\Services;

/**
 * A service "endpoint" in this context is the interfacing component of a service, which exposes its public API and is
 * designed to be used both locally, and exposed remotely over a network (for ex. HTTP).
 * 
 * A service exposes one or more endpoints.
 * 
 * A client uses a service by instantiating the service class (the root endpoint) and accessing any other endpoints
 * provided by it (exposed as public properties), and not by instantiating them directly.
 */
interface Endpoint {
	/**
	 * Returns one of two types of members based on the passed name:
	 * 
	 * - A public property holding a Endpoint instance.
	 * - A method (as a Closure instance) which takes a dict (or nothing) and return a dict (or nothing) or throws a
	 * EndpointException on error.
	 * - Null if the name doesn't resolve to either of the above.
	 * - NEW: Now it's correct behavior for an action to optionally return an endpoint. This allows fluent service APIs. 
	 * Avoid creating endpoint-returning actions which are slow or produce side-effects. This includes returning 
	 * endpoints which are not local to the action returning them (i.e. don't make a fluent chain "distributed"). Keep
	 * side-effects, heavy input and heavy processing for leaf actions!
	 * 
	 * Some reasons for encapsulating this logic as an interface method, instead of relying directly on reflection:
	 * 
	 * - It allows a service to expose "virtual" members which reflection won't see (for ex. __get & __call).
	 * - Allows a service to choose not to expose certain public members (like public magic methods).
	 * - By having one method for resolving properties and methods, it disallows name collision between them, making it
	 * easier to port a service to a language where such collisions are not allowed (Java, C#, etc).
	 * 
	 * Make sure to check the provided reusable implementation for possible different approaches to implementing this
	 * interface: StaticEndpoint, ProxyEndpoint & DeepProxyEndpoint, EmptyEndpoint etc. 
	 *  
	 * @param string $name
	 * Name of a public property or a method.
	 * 
	 * @return \Solver\Services\Endpoint|\Closure|null
	 * Endpoint instance, method as a closure, or null if the name does not resolve to either.
	 * 
	 * @throws \Solver\Services\EndpointException
	 * The endpoint may throw an exception if it wants to communicate details about the resolution failure, rather than
	 * simply returning null. But it's preferable that only actions (methods) throw this exception, and resolution does
	 * not result in exceptions. 
	 * 
	 * An endpoint with highly dynamic resolution rules, where names are not statically defined in the system may be a 
	 * candidate for throwing an exception here (but then we'd argue this should have been a simple action with
	 * parameters for the dynamic part).
	 */
	public function resolve($name);
	
	// TODO: Add support of this method for parametric endpoints (allows the endpoint to act as a constructor returning
	// and endpoint, as normal methods can't return endpoints). In URI's those parameters should be matrix parameters.
	// Change metrix params to always be a dictionary; if name not specified uses default name. Lists via commas tho?
	//public function with($context);
}