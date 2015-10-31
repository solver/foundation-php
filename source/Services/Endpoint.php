<?php
namespace Solver\Services;

/**
 * A service "endpoint" in this package is an object which can resolve names to other endpoints ("child endpoints") or 
 * actions. Child endpoints are also often exposed for native callers as properties, and actions as methods (which may
 * be dynamically resolved, or actual class properties or methods, depending on the implementation).
 * 
 * An endpoint is the interfacing component of a service, which exposes its public API and is* designed to be used both
 * locally, and exposed remotely over a network (for ex. HTTP).
 * 
 * Endpoints shouldn't be instantiated directly, but access only through its respective service/module object.
 * 
 * Actions returned by an endpoint can be any generic Action implementation (see \Solver\Accord\Action), with the
 * following additional constraints:
 * 
 * - The input and output of an endpoint Action MUST consist entirely out of value types (null, PHP scalars, PHP
 * array) and objects that implement \Solver\Accord\ToValue.
 * - The root of an input and output of an endpoint Action MUST be either null or a composite (PHP array), or an 
 * object that implements \Solver\Accord\ToValue, which resolves to a composite. 
 * - An endpoint action MUST not make a semantic distinction between an input/output value set to null, and a value that
 * is missing (either at the root, or nested at a deeper level). Preferably null shouldn't be used, except at the root.
 * - And endpoint action MAY also return a child endpoint. This allows for fluent service APIs that chain endpoints and
 * actions in a sequence. Avoid creating endpoint-returning actions which are slow or produce side-effects. Chaining 
 * should operate like function currying in functional languages. This also means actions should avoid returning
 * endpoints which are not local to the action returning them (i.e. don't make a fluent chain "distributed"). Keep
 * side-effects, heavy input and heavy processing for leaf actions.
 * 
 * These restrictions are designed to ensure best behavior and compatibility of endpoint actions on the network (as the
 * primary purpose of an endpoint is that it can be transparently accessed localy or remotely with the same semantics),
 * which implies serializable and well defined input/output, and clear interpretation of said input/output.
 */
interface Endpoint {
	/**
	 * Returns one of:
	 * 
	 * - An instance of Endpoint (a child endpoint of the current endpoint).
	 * - An instance of Action (\Solver\Accord\Action).
	 * - Null if the name doesn't resolve to either of the above.
	 * 
	 * Note that because endpoints and actions share a namespace at their parent endpoint, it's impossible to have 
	 * a child action and a child endpoint with the same name (unlike PHP which allows methods and properties with the
	 * same name).
	 * 
	 * Make sure to check the provided reusable implementations for possible different approaches to implementing this
	 * interface: ResolveViaMembers, MembersViaResolve, ProxyEndpoint & DeepProxyEndpoint, NullEndpoint etc. 
	 *  
	 * @param string $name
	 * Name of a child endpoint or a child action.
	 * 
	 * @return \Solver\Services\Endpoint|\Solver\Accord\Action|null
	 * Endpoint instance, Action instance, or null if the name does not resolve to either.
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
}