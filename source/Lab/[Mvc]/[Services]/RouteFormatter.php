<?php
namespace Solver\Lab;

/**
 * Changes a web-formatted (lowercase, dashes) set of route segments to a PHP-formatted (camelcased) set of route
 * segments and back.
 * 
 * Example:
 * 
 * - Web: ['foo-bar', 'baz-qux', 'qux']
 * - PHP: ['fooBar', 'barBaz', 'qux']
 */
class RouteFormatter {
	public static function webToPhp($route) {
		foreach ($route as & $seg) {
			// Validate, replace with null if invalid.
			if (preg_match('/[a-z](-[a-z0-9]+)*$/AD', $seg)) {
				if (strpos($seg, '-') !== false) {
					$seg = explode('-', $seg);
					
					for ($i = 1, $m = count($seg); $i < $m; $i++) {
						$seg[$i] = ucfirst($seg[$i]);
					}
					
					$seg = implode('', $seg);
				}
			} else {
				$seg = null;
			}
		}
		unset($seg);
		
		return $route;
	}
	
	public static function phpToWeb($route) {
		foreach ($route as & $seg) {
			// Validate, replace with null if invalid.
			if (preg_match('/[a-z][a-zA-Z0-9]*$/AD', $seg)) {
				$seg = strtolower(preg_replace('/[A-Z]/', '-$0', $seg));
			} else {
				$seg = null;
			}
		}
		unset($seg);
		
		return $route;
	}
}