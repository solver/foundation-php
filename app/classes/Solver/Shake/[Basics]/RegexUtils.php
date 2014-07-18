<?php
namespace Solver\Shake;

/**
 * Regex API for UTF8 encoded text. 
 * 
 * Important: This API is not binary safe, it's intended specifically for UTF8 strings.
 * 
 * All methods in this API assume the strings passed have been normalized NFC-style, which is the default normalization
 * style for the framework. You need to normalize strings that come from unknown sources (user input etc.).
 * 
 * @author Stan Vass
 * @copyright © 2012-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class RegexUtils {
	public static function match($subject, $pattern, array & $matches = null) {
		return \preg_match_all(self::utf8($pattern), $string, $matches, \PREG_SET_ORDER);
	}
	
	public static function matchAll($string, $pattern, array & $matches = null) {
		return \preg_match(self::utf8($pattern), $string, $matches);
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