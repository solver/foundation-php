<?php
namespace Solver\Base;

/**
 * FIXME: Chunks of the documentation is outdated.
 * 
 * This trait implements a convention for reusing a set of generalized magic methods, by defining simple "magic"
 * properties in order to create virtual object properties and methods (among other features).
 * 
 * The problem of defining magic methods directly is that they require more boilerplate, multiple parties (subclasses, 
 * traits) can't easily contribute get/set/call members once the methods are defined, and the resulting virtual members
 * are not inspectable and enumerable (reflection doesn't see them). Magic properties solve all those problems.
 * 
 * We've selected a naming convention with double underscore to avoid collisions with other object properties and align
 * with the precedent set by naming magic methods with double underscore.
 * 
 * Our goal is to create a trait which has very minimal to no impact on your object performance by default. Every magic
 * property feature is opt-in by setting the appropriate handlers and options.
 * 
 * Magic properties:
 * 
 * - $__handlers: A mapping of class member names to handlers [$name => $handlers].
 * - $__options: A bitwise mask of options.
 * 
 * Every $handlers value is a map from handler type to a handler definition:
 * 
 * - readonly: A value exposed as a read-only property.
 * - get: A getter closure (used if readonly key does not exist).
 * - set: A setter closure (can be combined with readonly instead of get).
 * - isset: An issetter closure (used if readonly key does not exist).
 * - unset: An unsetter closure.
 * - call: A call closure.
 * 
 * The following bitwise flags are available for the $__options mask:
 * 
 * - MagicOptions::EXPANDO: If true, enables setting new properties on the object from outside callers.
 * - MagicOptions::CALL_PROPERTIES: If true, exposes properties as methods (they must be callable).
 * - MagicOptions::GET_METHODS: If true, exposes methods as Closure properties to outside callers.
 * - MagicOptions::GET_METHODS_CACHE: If true, Magic stores fetched method closures as a call handler for next get.
 * 
 * If none of the rules above resolve, catchall entiries under $__handlers['*'] are invokes with the original magic
 * method arguments (which means $name is included).
 * 
 * Below is a detailed description of every magic property & its meaning:
 * 
 * TODO: Document trick of DEBUG-time only get/set assertions by unsetting public properties.
 * TODO: Document canonical order for resolving members when someone reads a member from multiple potential sources:
 * - Call-first scenarios: $__call -> $__readonly -> $__isset/$__unset -> $__get -> defined methods -> defined props
 * - Get-first scenarios: $__readonly -> $__isset/$__unset -> $__get -> $__call -> defined props -> defined methods
 * 
 * ---------------------------------------------------------------------------------------------------------------------
 * 
 * Semantics of handler name.readonly
 * 
 * A map of read-only properties: [$name => mixed $value]. 
 * 
 * Outside callers can read the values through object properties, but cannot set them. 
 * 
 * Read-only properties are resolved *before* getters, which means that if you define the same name under $__readonly
 * and $__get, callers will receive the $__readonly property. Among other benefits, this provides a simple mechanism for
 * creating performant "lazily created" property values, where you define a factory closure under $__get, and on first
 * call, it stores its result under $__readonly (for future calls) and then returns it.
 * 
 * Read-only properties are resolved *after* setters, which means you can have a setter to filter and validate the 
 * value being set, and the setter can assign it to $__readonly[$name] for direct performant reading.
 * 
 * ---------------------------------------------------------------------------------------------------------------------
 * 
 * Semantics of handler name.get
 * 
 * A map of property getters: [$name => \Closure $getter].
 * 
 * A getter closure receives no arguments, and must return the property value.
 * 
 * ---------------------------------------------------------------------------------------------------------------------
 * 
 * Semantics of handler name.set
 * 
 * A map of property setters: [$name => \Closure $setter].
 * 
 * A setter closure receives a single argument with the property value to be set.
 * 
 * ---------------------------------------------------------------------------------------------------------------------
 * 
 * Semantics of handler name.isset
 * 
 * A map of property issetters: [$name => \Closure $issetter].
 * 
 * An issetter closure receives no arguments and must return boolean true if the value is set, false if not.
 * 
 * An issetter is a complement to a getter, it's not invoked for read-only properties. TODO: Explain this better.
 * 
 * Note that defining issetters is optional, and at best should be considered an optimization in some edge cases, 
 * where computing a property (through a getter) is expensive. When an issetter is not defined, MagicProperties
 * automatically defaults to returning true if the property exists and is not null, or false otherwise.
 * 
 * ---------------------------------------------------------------------------------------------------------------------
 * 
 * Semantics of handler name.readonly
 * 
 * A map of property unsetters: [$name => \Closure $unsetter].
 * 
 * An unsetter closure receives no arguments and unsets the property it represents.
 * 
 * An unsetter is a complement to a setter.
 * 
 * Note that defining unsetters is optional. When an unsetter is not defined, MagicProperties will treat the property
 * as one that can't be unset and throw an error on any attempts to do so.
 * 
 * ---------------------------------------------------------------------------------------------------------------------
 * 
 * Semantics of handler name.call
 * 
 * A map of methods: [$name => \Closure $method].
 * 
 * A method closure receives its arguments "natively", as the original caller has specified them (and not as an 
 * array as the PHP __call metod receives them) and its result, if any, is returned to the caller.
 * 
 * TODO: Describe behavior with args by-ref and return by-ref.
 * 
 * ---------------------------------------------------------------------------------------------------------------------
 * 
 * Option EXPANDO
 * 
 * Allows outside callers to create new public properties on the object, default for PHP when not using MagicProperties.
 * 
 * Default false.
 * 
 * ---------------------------------------------------------------------------------------------------------------------
 * 
 * Option GET_METHODS
 * 
 * If true, allows public instance methods and $__call methods to be accessed as properties (of type \Closure).
 * 
 * Notice there is no corresponding $__setMethods, as interpreting this would be ambiguous. Instead, have a setter which
 * does the correct transform of $__readonly, $__get and $__call as necessary.
 * 
 * Default false.
 * 
 * ---------------------------------------------------------------------------------------------------------------------
 * 
 * Option GET_METHODS_CACHE
 * 
 * If true, then when a method is being fetched as a Closure property (see $__getMethods), the reference will be stored
 * under the relevant name in $__call for faster subsequent retrieval.
 * 
 * Default false.
 * 
 * ---------------------------------------------------------------------------------------------------------------------
 * 
 * Optional CALL_PROPERTIES
 * 
 * If true, allows public instance properties, $__readonly and $__get properties to be called as methods.
 * 
 * Default false.
 * 
 * ---------------------------------------------------------------------------------------------------------------------
 * 
 * Semantics of wildcard handlers under name "*" (get, set, isset, unset, call)
 * 
 * You can define & set any of these properties to a closure which emulates the respective original PHP magic methods,
 * and MagicProperties will call them with lowest priority after all other resolution attempts have failed.
 * 
 * Do note the signatures of these closures will receive the name of the property as first argument, and for $__callRest
 * the arguments are sent in the form of an array as the second argument (same as the classic __call method).
 * 
 * Avoid using these catch-alls, as they - in part - defeat the purpose of MagicProperties in making virtual properties
 * and methods easily inspectable and extensible.
 * 
 * IMPORTANT: When you define a $__*Rest property, keep in mind it's now your responsibility to throw an appropriate
 * exception or error if your custom logic doesn't find a property/callable to resolve to.
 */
trait Magic {
	protected $__handlers = [];
	protected $__options = 0;
	
	// TODO: Consider adding wildcard fallbacks in __get and __set etc. with lowest priority, which get the name being
	// handles so they can have generic code (not recommended to use it unless crucial though). We can have properties
	// for those like __getFallback etc.
	
	// TODO: The __readonly/__get overlap is hurting us performance wise, we need to always check __readonly before
	// __get. Can we avoid this by redesigning the format of the magic properties? complicating the format is not 
	// desirable, for example $__get[foo] = [isValue, valueOrGetter] is not desirable.
	
	// TODO: In some places we must check isset($this->__foo) before we check isset($this->__foo[$name]) to avoid
	// triggering nested __get calls. This seems like a problem in PHP, that's been reported. If fixed, simplify the
	// code.
	
	/**
	 * Canonical order of resolution:
	 * 
	 * 1. If name.readonly handler exists, returns contained value.
	 * 2. If name.get handler exists, calls getter and returns result value.
	 * 3. If flag GET_METHODS is set and name.call handler exists, returns contained closure.
	 * 4. If flag GET_METHODS is set and public/non-abstract/non-static method name exists, returns a closure of it.
	 * 5. If *.get is defined, calls it and returns the result.
	 * 6. Throws.
	 * 
	 * TODO: Try by-ref.
	 * 
	 * @param mixed $name
	 * @return mixed
	 * @throws \Exception
	 */
	public function __get($name) {
		$handlers = isset($this->__handlers[$name]) ? $this->__handlers[$name] : null;
				
		if (isset($handlers['readonly'])) {
			return $handlers['readonly'];
		}
		
		// We need key_exists() to detect null.
		if (key_exists($name, $handlers)) {
			return null;
		}
		
		if (isset($handlers['get'])) {
			return $handlers['get']();
		}
		
		$options = $this->__options;
		
		if ($options & MagicOptions::GET_METHODS) {
			if (isset($handlers['call'])) {
				return $handlers['call'];
			}
			
			if (method_exists($this, $name)) {
				$reflMethod = new \ReflectionMethod($this, $name);
				
				if ($reflMethod->isPublic() && !$reflMethod->isStatic() && !$reflMethod->isAbstract()) {
					$closure = $reflMethod->getClosure($this);
					
					if ($options & MagicOptions::GET_METHODS_CACHE) {
						$this->__handlers[$name]['call'] = $closure;
					}
					
					return $closure;
				}
			}
		}
		
		if (isset($this->__handlers['*']['get'])) {
			return $this->__handlers['*']['get']($name);
		}
		
		// TODO: Better class to throw (TypeError in PHP7?).
		throw new \Exception('Cannot get undefined property "' . $name . '".');
	}
	
	/**
	 * Canonical order of resolution:
	 * 
	 * 1. If name.set key exists, calls setter with value, returns.
	 * 2. If flag EXPANDO is set, and the name is not a property, no readonly/get handler, sets a new property, returns.
	 * 3. If *.set is defined, calls it and returns.
	 * 4. Throws.
	 * 
	 * TODO: Try by-ref.
	 * 
	 * @param string $name
	 * @param mixed $value
	 * @throws \Exception
	 */
	public function __set($name, $value) {
		$handlers = isset($this->__handlers[$name]) ? $this->__handlers[$name] : null;
		
		if (isset($handlers['set'])) {
			$handlers['set']($value);
			return;
		}
		
		// We need key_exists() to detect null.
		if (isset($handlers['get']) || isset($handlers['readonly']) || key_exists('readonly', $handlers)) {
			// Better class to throw (TypeError in PHP7?).
			throw new \Exception('Cannot set read-only property "' . $name . '".');
		} 
		
		if (property_exists($this, $name)) {
			// Better class to throw (TypeError in PHP7?).
			throw new \Exception('Cannot set non-public property "' . $name . '".');
		} 
		
		$options = $this->__options;
		
		if ($options & MagicOptions::EXPANDO) {
			$this->{$name} = $value;
			return;
		}
				
		if (isset($this->__handlers['*']['set'])) {
			$this->__handlers['*']['set']($name, $value);
			return;
		}
		
		// TODO: Better class to throw (TypeError in PHP7?).
		// TRICKY: We know it's "undefined" as we've done the checks above, before the expando code.
		throw new \Exception('Cannot set undefined property "' . $name . '".');
	}
	
	/**
	 * Canonical order of resolution:
	 * 
	 * 1. If name.readonly name exists, returns true if it's not null, returns false if it is null. 
	 * 2. If name.isset name exists, calls issetter and returns result value.
	 * 3. If name.get name exists, calls getter and returns true if it's not null, returns false if it is null.
	 * 4. If flag GET_METHODS is set and name.call or public/non-abstract/non-static method name exists, returns true.
	 * 5. If *.isset is defined, calls it and returns the result.
	 * 6. Returns false.
	 * 
	 * @param string $name
	 * @return bool
	 * @throws \Exception
	 */
	public function __isset($name) {
		$handlers = isset($this->__handlers[$name]) ? $this->__handlers[$name] : null;
		
		if (isset($handlers['readonly'])) {
			return true;
		}
		
		// We need key_exists() to detect null.
		if (key_exists('readonly', $handlers)) {
			return false; 
		}
		
		if (isset($handlers['isset'])) {
			return $handlers['isset']();
		}
		
		if (isset($handlers['get'])) {
			return $handlers['get']() !== null;
		}
		
		$options = $this->__options;
		
		if ($options & MagicOptions::GET_METHODS) {
			if (method_exists($this, $name)) {
				$reflMethod = new \ReflectionMethod($this, $name);
				
				if ($reflMethod->isPublic() && !$reflMethod->isStatic() && !$reflMethod->isAbstract()) {
					if ($options & MagicOptions::GET_METHODS_CACHE) {
						$this->__handlers[$name]['call'] = $reflMethod->getClosure($this);
					}
					
					return true;
				}
			}
		}	
		
		if (isset($this->__handlers['*']['isset'])) {
			$this->__handlers['*']['isset']($name);
			return;
		}
		
		return false;
	}
	
	/**
	 * Canonical order of resolution:
	 * 
	 * 1. If name.unset key exists, calls unsetter with value, returns.
	 * 3. If *.unset is defined, calls it and returns.
	 * 4. Throws.
	 * 
	 * NOTE: When you have expando properties, this handler isn't called, PHP handles this (these properties are always
	 * unsettable from outside).
	 * 
	 * @param string $name
	 * @return bool
	 * @throws \Exception
	 */
	public function __unset($name) {
		$handlers = isset($this->__handlers[$name]) ? $this->__handlers[$name] : null;
		
		if (isset($handlers['unset'])) {
			$handlers['unset']($name);
			return;
		}
		
		if (isset($this->__handlers['*']['unset'])) {
			$this->__handlers['*']['unset']($name);
			return;
		}
		
		// TODO: Better class to throw (TypeError in PHP7?).
		$defined = isset($handlers['get']) || isset($handlers['readonly']) || key_exists('readonly', $handlers);
		throw new \Exception('Cannot unset ' . ($defined ? '' : 'undefined ') . 'property "' . $name . '".');
	}
	
	/**
	 * Canonical order of resolution:
	 * 
	 * 1. If name.call exists, calls contained closure, returns result. 
	 * 2. If flag CALL_PROPERTIES is true, name.readonly name exists, calls it, returns result.
	 * 3. If flag CALL_PROPERTIES is true, name.get exists, calls it...  calls the result, returns the result's result.
	 * 4. If flag CALL_PROPERTIES is true, a public/non-static property with the name exists, calls it, returns result.
	 * 5. If *.call is defined, calls it and returns the result.
	 * 6. Throws.
	 * 
	 * TODO: Try by-ref.
	 * 
	 * @param string $name
	 * @param array $arguments
	 * @return mixed
	 * @throws \Exception
	 */
	public function __call($name, $arguments) {
		$handlers = isset($this->__handlers[$name]) ? $this->__handlers[$name] : null;
		
		if (isset($handlers['call'])) {
			return $handlers['call'](...$arguments);
		}
		
		$options = $this->__options;
		
		if ($options & MagicOptions::CALL_PROPERTIES) {
			// We skip key_exists() check here, null properties are an "undefined method" if called.
			if (isset($handlers['readonly'])) {
				return $handlers['readonly'](...$arguments);
			}
			
			if (isset($handlers['get'])) {
				$prop = $handlers['get']();
				return $prop(...$arguments);
			}
			
			if (property_exists($this, $name)) {
				$reflProp = new \ReflectionProperty($this, $name);
				
				if ($reflProp->isPublic() && !$reflProp->isStatic()) {
					return $this->{$name}(...$arguments);
				}	
			}
		}
	
		if (isset($this->__handlers['*']['call'])) {
			return $this->__handlers['*']['call']($name, $arguments);
		}
		
		// Better class to throw (TypeError in PHP7?).
		throw new \Exception('Cannot call undefined method "' . $name . '".');
	}
}