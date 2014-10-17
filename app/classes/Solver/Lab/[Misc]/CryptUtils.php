<?php
namespace Solver\Lab;

/**
 * Assists in creating and checking properly salted blowfish password hashes (using crypt()).
 * 
 * @author Stan Vass
 * @copyright © 2012-2013 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class CryptUtils {
	/**
	 * Returns cryptographically strong random bytes for use in hash salts and other.
	 * 
	 * @param int $length
	 * Number of bytes to return.
	 * 
	 * @param bool $allowWeak
	 * Optional (default = false). If the function can't access a single source of crypto strong random bytes, it'll
	 * throw an exception. But if this flag is true, it'll instead fall back to the weaker (for crypto) mt_rand API and
	 * return a valid result.
	 * 
	 * @param string
	 * Random bytes as a binary string.
	 */
	public static function getRandomBytes($length, $allowWeak = false) {
		if (\function_exists('mcrypt_create_iv')) {
			return \mcrypt_create_iv($length, \MCRYPT_DEV_URANDOM);
		}
	
		if (\function_exists('openssl_random_pseudo_bytes')) {
			$bytes = \openssl_random_pseudo_bytes($length, $strong);
			if ($strong) return $bytes;
		}
				
		if (\file_exists('/dev/urandom') && is_readable('/dev/urandom')) {
			$file = \fopen('/dev/urandom', 'r');
			$bytes = \fread($file, $length);
			\fclose($file);
			return $bytes;
		}
		
		if ($allowWeak) {
			// TRICKY: If $bytes is set, it means openssl returned a result, but flag $allowWeak was true. In this case 
			// we can use that result now.
			if (isset($bytes)) {
				return $bytes;
			} else {
				$bytes = '';
				for ($i = 0; $i < $length; $i++) {
					$bytes .= chr(\mt_rand(0, 255));
				}  
				return $bytes;
			}
			
		} else {
			throw new \Exception('No source of cryptographically strong random data found. Install openssl or mcrypt.');
		}
	}
	
	/**
	 * Returns a cryptographycally strong random salt for blowfish hashing.
	 * 
	 * @param bool $allowWeak
	 * Optional (default = false). If the function can't access a single source of crypto strong random bytes, it'll
	 * throw an exception. But if this flag is true, it'll instead fall back to the weaker (for crypto) mt_rand API and
	 * return a valid result.
	 * 
	 * @param string
	 * A random blowfish salt as a string, exactly 22 chars.
	 */
	protected static function getBlowfishSalt($allowWeak = false) {
		return \str_replace('=', '$', \str_replace('+', '.', \base64_encode(self::getRandomBytes(16, $allowWeak))));
	}
	
	/**
	 * Returns Unix crypt()-compatible randomly salted Blowfish hash for the given string. Suitable for password
	 * hashing.
	 * 
	 * @param string $string
	 * A string to hash (for example, a plain text password).
	 * 
	 * @param int $rounds
	 * Optional (default = 6). Number of rounds (power of 2) for the Blowfish algorithm.
	 * 
	 * @return string
	 * A crypt()-compatible Blowfish hash, exactly 60 chars (binary).
	 */
	public static function getBlowfishHash($string, $rounds = 6) {
		return \crypt($string, '$2y$' . ($rounds < 10 ? '0' . $rounds : $rounds) . '$' . self::getBlowfishSalt());
	}
	
	/**
	 * Verifies a string against a Unix crypt()-compatible Blowfish hash.
	 * 
	 * @param string $string
	 * A string to verify.
	 * 
	 * @param int $hash
	 * Hash to verify against the string.
	 * 
	 * @return bool
	 * True if the computed string hash matches the given hash, false if it doesn't.
	 */
	public static function verifyBlowfishHash($string, $hash) {
		return $hash === \crypt($string, $hash);
	}
	/**
	 * TODO: Pending full security assessment.
	 * TODO: Move this out into a separate class, this is not a generic tool, and not a generic encryption process, but
	 * a specific implementation, for a specific purpose (tokens).
	 * 
	 * A simple encryption tool for small messages such as login tokens (for ex. holding user id & expiration date).
	 * Built-in random salt/IV & MAC for 22 bytes of overhead (in total) over your message length.
	 * 
	 * The possibility for returning a tampered message as valid during decryption is 1 / (2 ^ 128), which is considered
	 * quite sufficient for simple security tokens and other short messages. The application can perform additional 
	 * actions to improve security, by say, blocking clients who give tokens that decrypt() detects as invalid (i.e. the
	 * method returns boolean false). You can also validate the basic data format of a message, depending on what you
	 * expect in it.
	 * 
	 * The balance of this algorithm is to confirm you as the origin of the message and prevent tampering with its 
	 * contents. While the content is encrypted as securely as possible, the mechanism is simple & focused on high
	 * performance, so very highly sensitive information shouldn't be encrypted using this method.
	 * 
	 * @param string $message
	 * Any string of bytes to encrypt. You can pad your data to a fixed length, in order to avoid leaking
	 * the relative length in the output (if it matters).
	 * 
	 * @param string $key
	 * A string of bytes to use as a secret key to decrypt the data. Important tips for generating safe keys:
	 * 
	 * - The best key is at least as long as your message. Failing that, make it at least 64 bytes long.
	 * - Don't use text or other predictable content for your key, use method getRandomBytes() or similar.
	 * - You can make a PHP snippet out of getRandomBytes() to paste in your code with method bytesToPhpLiteral().
	 * - Using a key to encode messages and then deciding to add bytes to it for longer messages will cause the old
	 * messages not to decrypt with the extended key (in case it needed saying). Decrypt with the exact key used in the
	 * encryption.
	 * 
	 * @return string
	 * Returns raw encrypted bytes (use base 64 or hex encoding if you want to transmit this through a binary unsafe
	 * environment). The length will be the length of your $data + 14 bytes for the automatic salt & mac string.
	 */
	public static function encrypt($message, $key) {
		// For MD5 hashes, 48 bits is sufficient salt size.
		$salt = self::getRandomBytes(6);
		
		$message = $message ^ self::getKeystream($salt, $key, \strlen($message));
		
		$mac = \hash_hmac('md5', $salt . $message, $key, true);
		
		return $salt . $message . $mac;
	}
	
	/**
	 * Decrypts a string encrypted with encrypt().
	 * 
	 * @param string $ciphertext
	 * Encrypted string of bytes.
	 * 
	 * @param string $secret
	 * The same secret string of bytes used during encryption.
	 * 
	 * @return string
	 * Decrypted bytes, or false (if MAC doesn't match, or malformed input is detected).
	 */
	public static function decrypt($ciphertext, $key) {
		$ciphertextLength = \strlen($ciphertext);
		
		if ($ciphertextLength < 22) return false;
		
		$salt = \substr($ciphertext, 0, 6);
		$message = \substr($ciphertext, 6, -16);
		$mac = \substr($ciphertext, -16);
		
		if ($mac !== \hash_hmac('md5', $salt . $message, $key, true)) return false;
		
		return $message ^ self::getKeystream($salt, $key, $ciphertextLength - 22);
	}
	
	/**
	 * Support code for encrypt()/decrypt().
	 * 
	 * @param string $salt
	 * @param string $key
	 * @param int $length
	 * @return string
	 */
	protected static function getKeystream($salt, $key, $length) {
		$keyLength = \strlen($key);
		
		if ($length > $keyLength) {
			$repeat = \ceil($length / $keyLength);
			$key = \str_repeat($key, $repeat);
			$keyLength *= $repeat;
		}
		
		$keyHash = '';
		 
		for ($i = 0; $i < $keyLength; $i += 16) {
			// Adding $i to the mix is a dirty way to fix a situation where the key is so small, it's repeated (and so 
			// the same substring gets hashed over and over). Naturally, it's better to just have a long enough key so 
			// it doesn't have to repeat, however it's not practical for longer messages.
			$keyHash .= \md5(\substr($key, $i, 16) . $salt . $i, true);
		}
		
		return $key ^ $keyHash;
	}
	
	/**
	 * Takes a string of bytes and produces a valid, readable PHP string literal (i.e. think var_export() optimized for 
	 * strings made of random bytes).
	 * 
	 * This is useful for code generation or for generating secret key snippets to paste in your code.
	 */
	public static function bytesToPhpLiteral($bytes) {
		return '"\x' . \trim(\chunk_split(\bin2hex($bytes), 2, '\x'), '\x') . '"';
	}
}