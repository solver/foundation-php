<?php
/*
 * Copyright (C) 2011-2015 Solver Ltd. All rights reserved.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at:
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations under the License.
 */
namespace Solver\Lab;

/**
 * A helper which can generate a class implementing/extending any class or interface list, with dynamically settable
 * methods and properties.
 * 
 * It's intended for test mock generation, and as a substitute for real anonymous classes until PHP7 is popular enough.
 * 
 * All abstract and interface methods in the object will be implemented with stubs, so you don't have to specify every
 * one of them (only those you expect to be called). Stubs throw "method not implemented" if called, or you can provide
 * your own stubs via the resolver() method.
 * 
 * Keep in mind eval() will be used (safe, but a bit slow).
 */
class AnonClass {
	private static $count = 0;
	private $partial = false;
	private $resolver = null;
	private $params;
	private $extend = null;
	private $implement = [];
	private $properties = [];
	private $methods = [];
	private $staticProperties = [];
	private $staticMethods = [];
	
	/**
	 * Begin the process of creating a new instance of anon class.
	 * 
	 * @param mixed[] ...$params
	 * Optional. Pass a list of parameters to be given to the class constructor. Note that arguments are captured
	 * by value by default. To pass a parameter by reference, pass an array with references using the variadic operator.
	 * Like so: begin(...[& $a, & $b, 'foo', 'bar']).
	 * 
	 * @return AnonClass
	 * Call the fluent methods of AnonClass to configure your class properties and methods. Don't forget to call
	 * end() as your last method, which will build the anonymous class object.
	 */
	public static function begin(& ...$params) {
		return new self($params);
	}
	
	/**
	 * Use AnonClass::begin().
	 */
	protected function __construct(array & $params) {
		$this->params = & $params;
	}

	/**
	 * Call this method if you want AnonClass to automatically add "stub methods" for all abstract and interface
	 * methods in the class you extend and interface you implement. This allows you to quickly prototype and mock
	 * objects while only implementing methods you expect to be needed (part of an interface for ex.)
	 * 
	 * When called, the stubs will throw an exception with message like "Method fooBar() is not implemented.", unless
	 * differently behaving stubs are provided by your custom resolver (see resolver()).
	 * 
	 * If you don't call this method, the default is to require full abstract method & interface implementation.
	 * 
	 * @param bool $partial
	 * Default = true. True if you want to implement abstract methods & interfaces partially, false to require full
	 * implementation.
	 * 
	 * @return $this
	 */
	public function partial($partial = true) {
		$this->partial = $partial;
		return $this;
	}
	
	/**
	 * Pass a method to be called, which can return implementations to abstract & interface properties not provided
	 * by your method() and staticMethod() calls.
	 * 
	 * This allows you to resolve methods using patterns and rules (for ex. all get*() methods, all set*() methods...).
	 * 
	 * Note that like method() and staticMethod() the implementations you return will be bound to the object being
	 * created, but the resolver itself will be left as-is.
	 * 
	 * @param \Closure $resolver
	 */
	public function resolver(\Closure $resolver) {
		$this->resolver = $resolver;
	}
	
	/**
	 * @param string $className
	 * @return $this
	 */
	public function extend($className) {
		$this->extend = $className;
		return $this;
	}
	
	/**
	 * @param string[] ...$interfaceNames
	 * @return $this
	 */
	public function implement(...$interfaceNames) {
		$this->implement = $interfaceNames;
		return $this;
	}
	
	/**
	 * @param string $name
	 * @param mixed $value
	 * @return $this
	 */
	public function property($name, $value = null) {
		$this->properties[$name] = $value;
		return $this;
	}
		
	/**
	 * @param string $name
	 * @param mixed $value
	 * @return $this
	 */
	public function staticProperty($name, $value = null) {
		$this->staticProperties[$name] = $value;
		return $this;
	}

	/**
	 * @param \Closure $implementation
	 * @return $this
	 */
	public function constructor(\Closure $implementation) {
		$this->methods['__construct'] = $implementation;
		return $this;
	}

	/**
	 * @param \Closure $implementation
	 * @return $this
	 */
	public function destructor(\Closure $implementation) {
		$this->methods['__destruct'] = $implementation;
		return $this;
	}
	
	/**
	 * @param string $name
	 * @param \Closure $implementation
	 * @return $this
	 */
	public function method($name, \Closure $implementation) {
		$this->methods[$name] = $implementation;
		return $this;
	}
	
	/**
	 * @param string $name
	 * @param \Closure $implementation
	 * @return $this
	 */
	public function staticMethod($name, \Closure $implementation) {
		$this->staticMethods[$name] = $implementation;
		return $this;
	}
	
	/**
	 * Returns an instance of the anonymous class as configured. Don't forget to call this as your last method after
	 * using the other configuration methods of the class.
	 * 
	 * @return AnonClass
	 */
	public function end() {
		$partial = $this->partial;
		$resolver = $this->resolver;
		$params = & $this->params;
		$extend = $this->extend;
		$implement = $this->implement;
		$properties = $this->properties;
		$staticProperties = $this->staticProperties;
		$methods = $this->methods;
		$staticMethods = $this->staticMethods;
		
		$className = 'AnonClass' . self::$count++;
		$head = '';
		$head .= 'namespace ' . (__NAMESPACE__ ? __NAMESPACE__ . ' ' : '') . "{\n\t";
		$head .= 'class ' . $className . ' ';
		$methodsCode = []; // We index by name to naturally weed out duplicates (only one definition can win).
		
		/*
		 * Validate property / member names to prevent accidental injection opportunities.
		 */
		
		$members = [
			'property' => $properties,
			'static property' => $staticProperties, 
			'method' => $methods,
			'static method' => $staticMethods,
		];
		
		foreach ($members as $type => $members) if ($members) {
			foreach ($properties as $n => $v) if (!preg_match('@[a-z_]\w*$@ADi', $n)) throw new \Exception('Invalid ' . $type . ' name "' . $n . '".');
		}
		
		/*
		 * Gather method definitions.
		 */
		
		if ($this->partial) {
			if ($implement) foreach ($implement as $interface) {
				$reflClass = new \ReflectionClass($interface);
				$methodsCode = $this->getAbstractMethods(true, $reflClass) + $methodsCode;
			}	
			
			if ($extend) {
				$reflClass = new \ReflectionClass($extend);
				$methodsCode = $this->getAbstractMethods(false, $reflClass) + $methodsCode;
			}	
		}
		
		if ($methods) foreach ($methods as $name => $impl) {
			if ($name === '__construct') continue; // Special handling in the <<<'CODE' nowdoc below.
			$reflFunc = new \ReflectionFunction($impl);
			$methodsCode[$name] = $this->getMethodProxy(false, $name, $reflFunc);
		}	
		
		if ($staticMethods) foreach ($staticMethods as $name => $impl) {
			$reflFunc = new \ReflectionFunction($impl);
			$methodsCode[$name] = $this->getMethodProxy(true, $name, $reflFunc);
		}	
		
		
		/**
		 * Resolve methods without a provided implementation.
		 */
		
		foreach ($methodsCode as $methodName => list($reflClass, $reflMethod)) {
			/* @var $reflClass \ReflectionClass */
			/* @var $reflMethod \ReflectionMethod */
			$isStaticMethod = $reflMethod->isStatic();
			$methodName = $reflMethod->getName();
			if ($resolver) {
				$methodCode = $resolver($reflClass->name, $methodName);
			} else {
				$methodCode = null;
			}
			
			if ($methodCode === null) {
				$methodCode = $this->getMethodProxy($isStaticMethod, $methodName, $reflMethod);
			}
		}
		
		/*
		 * Build class code.
		 */
		
		if ($extend) $head .= 'extends \\' . $extend;
		if ($implement) $head .= 'implements \\' . implode(', \\', $implement);
		
		$body = <<<'CODE'
		private static $__m, $__sm;
				
		private static function __ni($f) {
			throw new \Exception('Method ' . $f . '() is not implemented.');
		}
				
		public function __construct(& $params, & $m, & $sm, & $p, & $sp) {
			self::$__m = & $m;
			self::$__sm = & $sm;
			foreach ($sm as & $i) $i = $i->bindTo(null, __CLASS__);
			foreach ($m as & $i) $i = $i->bindTo($this, __CLASS__);
			foreach ($p as $n => $v) $this->{$n} = $v;
			foreach ($sp as $n => $v) self::${$n} = $v;
			if (isset($m['__construct'])) $m['__construct']->__invoke(...$params);
			elseif (is_callable('parent::__construct')) parent::__construct(...$params);
		}
CODE;
		$body .= "\n\n" . implode("\n", $methodsCode);
		eval($head . " {\n" . $body . "\n\t}\n}"); // <JonyIve>Beautifully, unapologetically eval</JonyIve>.
		
		/*
		 * Create object.
		 */
		
		return new $className($params, $methods, $staticMethods, $properties, $staticProperties);
	}
	
	private function getAbstractMethods($isInterface, \ReflectionClass $reflClass) {
		$methods = [];
		
		/* @var $reflMethod \ReflectionMethod */
		foreach ($reflClass->getMethods() as $reflMethod) {
			if ($isInterface || $reflMethod->isAbstract()) {
				$methods[$reflMethod->name] = [$reflClass, $reflMethod];
			}
		}
		
		return $methods;
	}
		
	private static function getMethodProxy($isStaticMethod, $methodName, \ReflectionFunctionAbstract $reflFunc) {
		$method = "\t\t" . 'public ';
		if ($isStaticMethod) $method .= 'static ';
		$method .= 'function ';
		if ($reflFunc->returnsReference()) $method .= '& ';
		$method .= $methodName;
		
		$params = '';
		$paramsForCall = '';
		
		/* @var $reflParam \ReflectionParameter */
		foreach ($reflFunc->getParameters() as $reflParam) {
			$paramName = $reflParam->getName();
			$param = '';
			$paramForCall = '';
			
			if ($reflParam->isArray()) $param .= 'array ';
			elseif ($reflParam->isCallable()) $param .= 'callable';
			elseif ($reflHintClass = $reflParam->getClass()) $param .= '\\' . $reflHintClass->name . ' ';
			
			if ($reflParam->isVariadic()) {
				$param .= '...';
				$paramForCall .= '...';
			}
			
			if ($reflParam->isPassedByReference()) $param .= '& ';
			
			$param .= '$' . $paramName;
			$paramForCall .= '$' . $paramName;
			
			if ($reflParam->isDefaultValueAvailable()) {
				$param .= ' = ';
				
				if ($reflParam->isDefaultValueConstant()) {
					$param .= $reflParam->getDefaultValueConstantName();
				} else {
					$param .= var_export($reflParam->getDefaultValue(), true);
				}
			} elseif ($reflParam->isOptional()) {
				$param .= ' = null';
			}
							
			$param .= ', ';
			$paramForCall .= ', ';
			
			$params .= $param;
			$paramsForCall .= $paramForCall;
		}
		
		$params = trim($params, ' ,');
		$paramsForCall = trim($paramsForCall, ' ,');
		
		$method .= '(' . $params . ') { ';
		$method .= "\n\t\t\t" . '$m = & self::$__' . ($isStaticMethod ? 'sm' : 'm') . '; if (isset($m[__FUNCTION__])) return $m[__FUNCTION__]->__invoke(' . $paramsForCall . '); else self::__ni(__FUNCTION__);' . "\n\t\t}\n";
		
		return $method;
	}
}