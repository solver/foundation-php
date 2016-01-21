<?php
namespace Solver\Base\KeywordMethodsForPhp5;

/**
 * In PHP7 class members can be named after any PHP reserved word: https://wiki.php.net/rfc/context_sensitive_lexer
 * 
 * In PHP5 this is not the case. This trait provides a stop-gap measure by mapping an illegal method name, like list(),
 * to a protected method list_see_KeywordMethodsForPhp5(). Unfortunately this only works for instance methods (for 
 * static methods, calling keyword methods is a parse error).
 * 
 * The suffix "_see_KeywordMethodsForPhp5" is deliberately ugly and explicit, so developers encountering a class
 * using this trait can quickly orient themselves about what's going on. And... it'll serve as an obvious reminder to
 * remove the hack, when the code drops PHP 5.x support.
 */
trait KeywordMethodsForPhp5 {
	public static function __call($name, $args) {
		if (method_exists($this, $name . '_see_KeywordMethodsForPhp5')) {
			return $this->{$name . '_see_KeywordMethodsForPhp5'}(...$args);
		} else {
			// Call the non-existing method, so we fail as we could if __callStatic() wasn't defined.
			return $this->{$name}(...$args);
		}
	}
}