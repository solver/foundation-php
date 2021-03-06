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
namespace Solver\Toolbox;

/**
 * Utilities for working with regex for UTF8 encoded text. 
 * 
 * Important: This API is not binary safe, it's intended only for UTF8 strings.
 * 
 * All methods in this API assume the strings passed have been normalized NFC-style, which is the default normalization
 * style for the framework. You need to normalize strings that come from unknown sources (user input etc.).
 * 
 * TODO: Throw exception on PCRE errors (see legacy code).
 */
class RegexUtils {
	public static function match($string, $pattern) {
		if (\preg_match(self::utf8($pattern), $string, $matches)) {
			return $matches;
		} else {
			return [];
		}
	}
	
	public static function matchCount($string, $pattern) {
		return (int) \preg_match(self::utf8($pattern), $string);
	}
	
	public static function matchAll($string, $pattern) {
		if (\preg_match_all(self::utf8($pattern), $string, $matches, \PREG_SET_ORDER)) {
			return $matches;
		} else {
			return [];
		}
	}
	
	public static function escape($string, $delimiter = null) {
		return \preg_quote($string, $delimiter);
	}
	
	public static function replace($string, $pattern, $replacementOrClosure) {
		if ($replacementOrClosure instanceof \Closure) {
			return \preg_replace_callback(self::utf8($pattern), $replacementOrClosure, $string);
		} else {
			return \preg_replace(self::utf8($pattern), $replacementOrClosure, $string);
		}
	}
	
	public static function split($string, $pattern) {
		return \preg_split(self::utf8($pattern), $string);
	}
	
	/**
	 * Adds the necessary ornamentation to enable UTF8 support on the widest range of platforms.
	 */
	protected static function utf8($pattern) {
		// Basically a pattern like "/foo/i" will become "/(*UTF8)foo/iu".
		$delim = $pattern[0];
		$pattern[0] = ')';
		return $delim . '(*UTF8' . $pattern . 'u';
	}
}