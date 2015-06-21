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
namespace Solver\Sidekick;

use Solver\SqlX\SqlUtils;
// TODO: Verify $name, $internalName are composite or simple as their method requires.
class FieldTypes {
	public static function jsonCol($name, $internalName = null) {
		return [
			$name,
			$internalName,
			function ($valueList, $composite) {
				if ($composite) self::errorNoComposite($valueList);
				return array_map(function ($value) { return json_encode($value); }, $valueList);
			},
			function ($valueList, $composite) {
				if ($composite) self::errorNoComposite($valueList);
				return array_map(function ($value) { return json_decode($value, true); }, $valueList);
			}
		];
	}
	
	public static function boolCol($name, $internalName = null) {
		return [
			$name,
			$internalName,
			function ($valueList, $composite) {
				if ($composite) self::errorNoComposite($valueList);
				return array_map(function ($value) { return $value ? 1 : 0; }, $valueList);
			},
			function ($valueList, $composite) {
				if ($composite) self::errorNoComposite($valueList);
				return array_map(function ($value) { return $value ? true : false; }, $valueList);
			}
		];
	}
	
	public static function stringCol($name, $internalName = null) {
		$transform = function ($valueList, $composite) {
			if ($composite) self::errorNoComposite($valueList);
			$result = array_map(function ($value) { return (string) $value; }, $valueList);
			return $result;
		};
		
		return [$name, $internalName, $transform, $transform];
	}
	
	public static function intCol($name, $internalName = null) {
		$transform = function ($valueList, $composite) {
			if ($composite) self::errorNoComposite($valueList);
			return array_map(function ($value) { return (int) $value; }, $valueList);
		};
		
		return [$name, $internalName, $transform, $transform];
	}
	
	public static function floatCol($name, $internalName = null) {
		$transform = function ($valueList, $composite) {
			if ($composite) self::errorNoComposite($valueList);
			return array_map(function ($value) { return (float) $value; }, $valueList);
		};
		
		return [$name, $internalName, $transform, $transform];
	}
	
	public static function numberCol($name, $internalName = null) {
		$transform = function ($valueList, $composite) {
			if ($composite) self::errorNoComposite($valueList);
			return array_map(function ($value) { return +$value; }, $valueList);
		};
		
		return [$name, $internalName, $transform, $transform];
	}
	
	/**
	 * Timestamp integer on PHP's side, DATETIME string on SQL's side.
	 */
	public static function timestampToDateTimeCol($name, $internalName = null) {
		return [
			$name,
			$internalName,
			function ($valueList, $composite) {
				if ($composite) self::errorNoComposite($valueList);
				return array_map(function ($value) { return SqlUtils::toDatetime($value); }, $valueList);
			},
			function ($valueList, $composite) {
				if ($composite) self::errorNoComposite($valueList);
				return array_map(function ($value) { return SqlUtils::fromDatetime($value); }, $valueList);
			}
		];
	}
	
	// TODO: Add support for per-column codecs.
	// TODO: Return also the name, and internalName cols so one could use...
	public static function groupCol($name, $internalPrefix, $fields) {
		// TODO: Implement.
		throw new \Exception('Not implemented.');
	}
	
	protected static function errorNoComposite($valueList) {
		$keys = array_keys($valueList);
		throw new \Exception('Trying to use a simple-only transform in a composite transform context, for fields: "' . implode('", "', $keys) . '".');
	}
	
	protected static function errorNoExpr($valueList) {
		$keys = array_keys($valueList);
		// TODO: That sounds like Klingon, improve the message wording.
		throw new \Exception('Trying to use an Expr value in a no-Expr context, for fields: "' . implode('", "', $keys) . '".');
	}
	
	protected static function errorNoSimple() {
		throw new \Exception('Trying to use a composite-only transform in a simple transform context.');
	}
}