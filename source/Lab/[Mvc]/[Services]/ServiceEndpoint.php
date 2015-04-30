<?php
namespace Solver\Lab;

/**
 * A service "endpoint" in this context is the interfacing component of a service, which exposes its public API and is
 * designed to be used both locally, and exposed remotely over a network (for ex. HTTP).
 * 
 * A service exposes one or more endpoints.
 * 
 * A client uses a service by instantiating the service class (the root endpoint) and accessing any other endpoints
 * provided by it (exposed as public properties), and not by instantiating them directly.
 */
interface ServiceEndpoint {
	/**
	 * Returns one of two types of members based on the passed name:
	 * 
	 * - A public property holding a ServiceEndpoint instance.
	 * - A method (as a Closure instance) which takes a dict (or nothing) and return a dict (or nothing) or throws a
	 * ServiceEndpointException on error.
	 * - Null if the name doesn't resolve to either of the above.
	 * 
	 * Some reasons for encapsulating this logic as a method, instead of relying directly on reflection:
	 * 
	 * - It allows a service to expose "virtual" members which reflection won't see (for ex. __get & __call).
	 * - Allows a service to choose not to expose certain public members (like public magic methods).
	 * - By having one methods for resolving properties and methods, it disallows name collision between them, making it
	 * easier to port a service to a language where such collisions are not allowed (Java, C#, etc).
	 * 
	 * @param string $name
	 * Name of a public property or a method.
	 * 
	 * @return \Solver\Lab\ServiceEndpoint|\Closure|null
	 * Endpoint instance, method as a closure, or null if the name does not resolve to either. 
	 */
	public function resolve($name);
}