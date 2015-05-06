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
 * one of them (only those you expect to be called). Stubs throw "method not implemented" if called.
 * 
 * Keep in mind eval() will be used (safe, but a bit slow).
 */
class AnonClass {
	private static $count = 0;
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
	 * @param string $className
	 * @return self
	 */
	public function extend($className) {
		$this->extend = $className;
		return $this;
	}
	
	/**
	 * @param string[] ...$interfaceNames
	 * @return self
	 */
	public function implement(...$interfaceNames) {
		$this->implement = $interfaceNames;
		return $this;
	}
	
	/**
	 * @param string $name
	 * @param mixed $value
	 * @return self
	 */
	public function property($name, $value = null) {
		$this->properties[$name] = $value;
		return $this;
	}
		
	/**
	 * @param string $name
	 * @param mixed $value
	 * @return self
	 */
	public function staticProperty($name, $value = null) {
		$this->staticProperties[$name] = $value;
		return $this;
	}

	/**
	 * @param \Closure $implementation
	 * @return self
	 */
	public function constructor(\Closure $implementation) {
		$this->methods['__construct'] = $implementation;
		return $this;
	}

	/**
	 * @param \Closure $implementation
	 * @return self
	 */
	public function destructor(\Closure $implementation) {
		$this->methods['__destruct'] = $implementation;
		return $this;
	}
	
	/**
	 * @param string $name
	 * @param \Closure $implementation
	 * @return self
	 */
	public function method($name, \Closure $implementation) {
		$this->methods[$name] = $implementation;
		return $this;
	}
	
	/**
	 * @param string $name
	 * @param \Closure $implementation
	 * @return self
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
		
		$valid = '@[a-z_]\w*$@ADi';
		foreach ($properties as $n => $v) if (!preg_match($valid, $n)) throw new \Exception('Invalid property name "' . $n . '".');
		foreach ($staticProperties as $n => $v) if (!preg_match($valid, $n)) throw new \Exception('Invalid static property name "' . $n . '".');
		foreach ($methods as $n => $v) if (!preg_match($valid, $n)) throw new \Exception('Invalid method name "' . $n . '".');
		foreach ($staticMethods as $n => $v) if (!preg_match($valid, $n)) throw new \Exception('Invalid static method name "' . $n . '".');
		
		/*
		 * Gather method definitions.
		 */
		
		if ($implement) foreach ($implement as $interface) {
			$reflClass = new \ReflectionClass($interface);
			if (!$reflClass->isInterface()) throw new \Exception('You cannot implement a class, ' . $interface . '.');
			$methodsCode = $this->getAllMethodProxies(true, $reflClass) + $methodsCode;
		}	
		
		if ($extend) {
			$reflClass = new \ReflectionClass($extend);
			if ($reflClass->isInterface()) throw new \Exception('You cannot extend an interface, ' . $extend . '.');
			$methodsCode = $this->getAllMethodProxies(false, $reflClass) + $methodsCode;
		}	

		if ($methods) foreach ($methods as $name => $impl) {
			if ($name === '__construct') continue; // Special handling.
			$reflFunc = new \ReflectionFunction($impl);
			$methodsCode[$name] = self::getMethodProxy(false, $name, $reflFunc);
		}	
		
		if ($staticMethods) foreach ($staticMethods as $name => $impl) {
			$reflFunc = new \ReflectionFunction($impl);
			$methodsCode[$name] = self::getMethodProxy(true, $name, $reflFunc);
		}	
		
		/*
		 * Build class code.
		 */
		
		if ($extend) $head .= 'extends \\' . $extend;
		if ($implement) $head .= 'implements \\' . implode(', \\', $implement);
		
		$body = <<<'CODE'
		private static $__methods, $__staticMethods;
				
		private static function __notImplemented($function) {
			throw new \Exception('Method ' . $function . ' is not implemented.');
		}
				
		public function __construct(& $params, & $m, & $sm, & $p, & $sp) {
			self::$__methods = & $m;
			self::$__staticMethods = & $sm;
			foreach ($sm as & $i) $i = $i->bindTo(null, __CLASS__);
			foreach ($m as & $i) $i = $i->bindTo($this, __CLASS__);
			foreach ($p as $n => $v) $this->{$n} = $v;
			foreach ($sp as $n => $v) self::${$n} = $v;
			if (isset($m['__construct'])) $m['__construct']->__invoke(...$params);
			elseif (is_callable('parent::__construct')) parent::__construct(...$params);
		}


CODE;
		$body .= implode("\n", $methodsCode);
		eval($head . " {\n" . $body . "\n\t}\n}"); // <JonyIve>Unapologetically eval</JonyIve>.
		
		/*
		 * Create object.
		 */
		
		return new $className($params, $methods, $staticMethods, $properties, $staticProperties);
	}
	
	private static function getAllMethodProxies($isInterface, \ReflectionClass $reflClass) {		
		$methodsCode = [];
		
		/* @var $reflMethod \ReflectionMethod */
		foreach ($reflClass->getMethods() as $reflMethod) {
			if ($isInterface || $reflMethod->isAbstract()) {
				$isStaticMethod = $reflMethod->isStatic();
				$methodName = $reflMethod->getName();
				$methodsCode[$methodName] = self::getMethodProxy($isStaticMethod, $methodName, $reflMethod);
			}
		}
		
		return $methodsCode;
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
				$param .= '... ';
				$paramForCall .= '... ';
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
		
		if ($isStaticMethod) {
			$method .= "\n\t\t\t" . '$sm = & self::$__staticMethods; if (isset($sm[__FUNCTION__])) return $sm[__FUNCTION__]->__invoke(' . $paramsForCall . '); ';
			$method .= "\n\t\t\t" . 'else self::__notImplemented(__FUNCTION__);' . "\n\t\t}\n";
		} else {
			$method .= "\n\t\t\t" . '$m = & self::$__methods; if (isset($m[__FUNCTION__])) return $m[__FUNCTION__]->__invoke(' . $paramsForCall . '); ';
			$method .= "\n\t\t\t" . 'else self::__notImplemented(__FUNCTION__);' . "\n\t\t}\n";
		}
		
		return $method;
	}
}