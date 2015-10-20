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
namespace Solver\Guardian;

/**
 * It's recommended to run Guard::init() immediately after your autoloader is operational.
 * 
 * Protects against:
 * 
 * - Running on old PHP version.
 * - Running without essential extensions assumed to be present by Solver Foundation's libraries.
 * - Commonly wrong PHP.ini settings that can subtly or not so subtly break the app and damage data.
 * - Running after a notice or a warning is issued (converts to an exception).
 * 
 * TODO: This is a very minimal port from the internal codebase. Port the rest here.
 * 
 * TODO: Remove dependency on global DEBUG constant.
 */
class Guardian {
	/**
	 * Checks the system for proper settings, required extensions etc.
	 */
	public static function init($errorLogFile) {
		/*
		 * Check minimum requirements.
		 */
		
		if (\version_compare('5.6.0', PHP_VERSION) == 1) throw new \Exception('This code requires PHP 5.6.0 or later.');
		if (!\extension_loaded('intl')) throw new \Exception('This code requires extension "intl".');
		if (!\extension_loaded('iconv')) throw new \Exception('This code requires extension "iconv".');
		if (!\extension_loaded('mbstring')) throw new \Exception('This code requires extension "mbstring".');
		if (!\extension_loaded('intl')) throw new \Exception('This code requires extension "intl".');
		if (!\extension_loaded('gmp')) throw new \Exception('This code requires extension "gmp".');
		
		/*
		 * Fix common config problems.
		 */
		
		\error_reporting(-1);
		\ini_set('log_errors', !\DEBUG); // Log only on production server.
		\ini_set('error_log', $errorLogFile);
		\ini_set('display_errors', \DEBUG); // Display only on dev machines.
		if (!\ini_get('ignore_user_abort')) \ignore_user_abort(true);
		if (\ini_get('session.use_trans_sid')) \ini_set('session.use_trans_sid', false);
		
		// The defaults are typically 14 for precision, and 17 for serialize_precision. Using 14 leads to needless data
		// loss, as counterintuitively the 'precision' setting is used for many serialization contexts (json, sql etc.).
		// While in some edge cases 17 digits of precision are required to encode a double as a string exactly, it
		// produces false digits, which while they don't harm precision, produce noise and bloat up the size of the
		// serialized version of the number. Most implementations use 16 digits of precision for serializing doubles,
		// and they are defined as having 15.95 decimal digits of precision in IEEE 754, so we're using this as well.		
		if (\ini_get('precision') != 16) \ini_set('precision', 16);
		if (\ini_get('serialize_precision') != 16) \ini_set('serialize_precision', 16);
		
		/*
		 * Convert warnings, notices & other catchable errors (except E_DEPRECATED) to exceptions to:
		 * - Avoid risk of bad code continuing execution with undefined state and corrupting app state.
		 * - Get stack traces on warnings and notices.
		 * - Catching warnings and notices is useful in select cases (i.e. branching off of a failed include for ex.)
		 * 
		 * Note that:
		 * - Operator "@" is supported due to unfortunate inconsistent use of warnings/notices by internal PHP APIs.
		 * 
		 * TODO: Move back to specific exceptions (for I/O and includes).
		 */
		
		set_error_handler(function ($severity, $message, $file, $line) {
			// We have to support this to be compatible with edge cases requiring @, or muting E_DEPRECATED etc.
			if (error_reporting() & $severity) { 
				// Goes back to the default PHP handler.
				if ($severity === E_DEPRECATED) return false; 
				throw new \ErrorException($message, 0, $severity, $file, $line);
			} else {
				// No handler (error muted).
				return true; 
			}
		}, -1);
	}
}