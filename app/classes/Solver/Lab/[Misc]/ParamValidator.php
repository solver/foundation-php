<?php
/*
 * Copyright (C) 2011-2014 Solver Ltd. All rights reserved.
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
 * A quick and "dirty" way to validate complex input schemas. This is intended to catch developer mistakes in arrays
 * passed as configuration (and when arrays are used as a mechanism for passing named parameters to methods), and 
 * validate situations the PHP semantics don't cover (such as: an object instance that must implement multiple 
 * interfaces).
 * 
 * As such the intent of this validator is for it to be primarily used during development & testing, and disabled on 
 * production. Of course, you wouldn't do that for external and hence untrusted input.
 * 
 * IMPORTANT: If you're validating & filtering complex untrusted input (especially in a service), it's highly
 * recommended to use the existing Format classes, instead. They're built for that purpose.
 */
class ParamValidator {
	/**
	 * Validates a value according to the schema. 
	 * 
	 * The method doesn't return anything. On valid input it does nothing, on invalid method it throws an exception with
	 * an informative message. This is intended to simplify usage in typical scenarios.
	 * 
	 * @param string $name
	 * Optional. A name to use in a thrown Exception, purely for display/informative purposes.
	 * 
	 * You can skip the name if you want to pass an array where the root keys are to be considered the root parameters
	 * in reporting (say if you pass an array with every parameter in a method, assigning it to a key of its name).
	 * 
	 * @param array $value
	 * Array to validate.
	 * 
	 * @param mixed $schema
	 * 
	 * A schema is one of these:
	 * 
	 * - string: Where type is one of the supported values for type (see in the array syntax below).
	 * - dict: Where key "0" denotes type (so you don't have to type the key), and some types have optional additional 
	 * keys to pass extra options.
	 * 
	 * Supported types:
	 * 
	 * <code>
	 * [
	 * 		// Existence is enough, no checks. Null is valid for this, as well.
	 * 		'any', 
	 * ]
	 * 
	 * [
	 * 		// The value should be PHP null. Note the type value is string 'null', not the actual PHP null.
	 * 		'null', 
	 * ]
	 * 
	 * [
	 * 		// The value should be a scalar (PHP's is_scalar()).
	 * 		'scalar', 
	 * ]
	 * 
	 * [
	 * 		// The value should be any array (for more options, use "dict" or "list", instead).
	 * 		'array', 
	 * ]
	 * 
	 * [
	 * 		// The array should be 'indexed array', i.e. zero-based monotonously incrementing integer keys (no "holes").
	 * 		'list', 
	 * 
	 * 		// Optional. Supply a schema (same format as this root schema) to check every list item (i.e. "list of").
	 * 		'of' => $schema,
	 * ]
	 * 
	 * [
	 * 		// Any array is also a "dict", but there are extra options, see below.
	 * 		'dict', 
	 * 
	 * 		// A list of keys that must be present in the dict.
	 * 		'req' => [ 
	 * 			// You can supply any valid schema to check if that field matches it (nested schemas have an identical 
	 *			// format to the root one being described here).
	 * 			'foo' => $schema,
	 * 		],
	 * 
	 * 		// Optional. Keys that *may* be in the array (no need to list the required ones again).	You can pass $schema
	 * 		// for each key, or type just the key as a value, just like with 'required'. Any key not listed in
	 * 		// 'req' or 'opt' will trigged an exception.
	 * 		'opt' => [...],
	 * ]
	 * 
	 * [	
	 * 		// The value must be an object instance.
	 * 		'object',
	 * 
	 * 		// Optional. You can pass a type name (interface, class) or a list of type names and the object must be an
	 * 		// instance of all of them (not one of them).
	 * 		'is' => $typeName | [$typeName, $typeName, $typeName, ...],
	 * ]
	 * </code>
	 * 
	 * TODO: Add specific scalar types (consider semantics: int-like string vs. int etc.) and more constraints (like
	 * length on lists etc.).
	 * 
	 * @throws \Exception
	 */
	public static function validate($name = null, $value, $schema) {
		if (\is_string($schema)) $schema = [$schema];

		switch ($schema[0]) {
			case "any":
				return;
				
			case "null":
				if ($value !== null) self::errorMustBeType($name, 'null');
				break;
				
			case "scalar":
				if (!\is_scalar($value)) self::errorMustBeType($name, 'scalar');
				break;
				
			case "array":
				if (!\is_array($value)) self::errorMustBeType($name, 'array');
				break;
				
				
			case "list":
				if (!\is_array($value)) self::errorMustBeType($name, 'list');
				
				$i = 0;
				
				if (isset($schema['of'])) {
					$subschema = $schema['of'];
					$prefix = $name === null ? '' : $name . '.';
					
					foreach ($value as $subname => $subvalue) {
						if ($subname !== $i) self::error($name, 'must be a "list" with sequential, zero-based keys');
						
						self::validate($prefix . $subname, $subvalue, $subschema);
						
						$i++;
					}
				} else {
					foreach ($value as $subname => $subvalue) {
						if ($subname !== $i) self::errorMustBeType($name, 'must be a "list" with sequential, zero-based keys');
						
						$i++;
					}
				}				
				break;
			
			case "dict":
				if (!\is_array($value)) self::errorMustBeType($name, 'dict');
				
				$required = isset($schema['req']) ? $schema['req'] : [];
				$optional = isset($schema['opt']) ? $schema['opt'] : [];
				$prefix = $name === null ? '' : $name . '.';
				
				foreach ($value as $subname => $subvalue) {
					if (isset($required[$subname])) {
						$subschema = $required[$subname];
						
						// Shortcircuit the common "any"-as-string scheme, not to waste CPU.
						if ($subschema !== 'any') {
							self::validate($prefix . $subname, $subvalue, $subschema);
						}
						
						unset($required[$subname]);
					} 
					
					elseif (isset($optional[$subname])) {
						$subschema = $optional[$subname];
						
						// Shortcircuit the common "any"-as-string scheme, not to waste CPU.
						if ($subschema !== 'any') {
							self::validate($prefix . $subname, $subvalue, $subschema);
						}
					}
					
					else {
						self::error($name, 'contains an unexpected key "' . $subname . '"');
					}
				}
				
				if ($required) {
					if (\count($required) == 1) {
						$missing = \array_keys($required);
						self::error($name, 'is missing required key "' . $missing[0] . '"');
					} else {
						$missing = \array_keys($required);
						$missing = '"' . \implode('", "', $missing) . '"';
						self::error($name, 'is missing multiple required keys: ' . $missing);
					}
				}
				
				break;
				
			case "array":
				if (!\is_array($value)) self::errorMustBeType($name, 'array');
				break;
			
			case "object":
				if (!\is_object($value)) self::errorMustBeType($name, 'object');
				
				if (isset($schema['is'])) {					
					$instList = $schema['is'];					
					if (\is_string($instList)) $instList = [$instList];
					
					foreach ($instList as $inst) {
						var_dump($value, $inst);
						if (!($value instanceof $inst)) {
							self::error($name, 'must be an instance of "' . $inst . '"');
						}
					}
				}
				break;
						
			default:
				throw new \Exception('Bad schema type "'. $schema['type'] . '".');
		}
	}
	

	protected static function error($name, $requirement) {
		throw new \Exception('Parameter ' . ($name === null ? '' : '"' . $name . '" ') . ' ' . $requirement . '.');
	}
	
	protected static function errorMustBeType($name, $type) {
		self::error($name, 'must be of type "' . $type . '"');
	}
}