<?php
namespace Solver\Lab;

/**
 * A basic utility producing random keys using a predefined alphabet. Use this for producing random keys, tokens etc.
 * 
 * TODO: Implement additionals flags (or methods), which provide guarantees about cryptographical sanity, uniqueness
 * etc.
 * 
 * @author Stan Vass
 * @copyright © 2011-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class KeyMaker {
	/**
	 * Returns a new random key.
	 * 
	 * @param number $length
	 * Key length in characters.
	 * 
	 * @param string $alphabet
	 * Optional (default = null). A string of characters to use for creating the key. If you pass null (or nothing),
	 * the default alphabet used will be all lowercase and uppercase latin letters plus all digits (total of 62
	 * characters). The keys produced with the default alphabet can then be included verbatim without processing and
	 * escaping in a wide range of mediums and encodings (ASCII, UTF-8, 7-bit encoding, URLs etc.).
	 * 
	 * @return string
	 * Random key string.
	 */
	public static function getKey($length, $alphabet = null) {
		if ($alphabet === null) $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		
		$key = '';
		
		for ($i = 0; $i < $length; $i++) {
			$key .= $alphabet[\mt_rand(0, \strlen($alphabet) - 1)];
		}
		
		return $key;
	}
}