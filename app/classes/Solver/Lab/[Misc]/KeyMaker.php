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
 * A basic utility producing random keys using a predefined alphabet. Use this for producing random keys, tokens etc.
 * 
 * TODO: Implement additionals flags (or methods), which provide guarantees about cryptographical sanity, uniqueness
 * etc.
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
	 * characters). The keys produced with the default alphabet are selected to be usable verbatim without processing 
	 * and escaping in a wide range of mediums and encodings (ASCII, UTF-8, 7-bit encoding, URLs, JSON etc.).
	 * 
	 * @param bool $cryptoWeak
	 * Default false. Enable this to use a much faster and still sufficiently random, but cryptographically insecure
	 * algorithm. If you enable this flag and use the resulting keys as tokens, passwords etc. your application may be
	 * exploitable. 
	 * 
	 * @return string
	 * Random key string.
	 */
	public static function getKey($length, $alphabet = null, $cryptoWeak = false) {
		if ($alphabet === null) $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$alphabetLength = \strlen($alphabet);
		$key = '';
		
		if ($cryptoWeak) {
			for ($i = 0; $i < $length; $i++) {
				$key .= $alphabet[\mt_rand(0, $alphabetLength - 1)];
			}
		} else {
			$bytes = CryptUtils::getRandomBytes($length);
			
			for ($i = 0; $i < $length; $i++) {
				$key .= $alphabet[ord($bytes[$i]) % $alphabetLength];
			}
		}
		
		return $key;
	}
}