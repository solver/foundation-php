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
namespace Solver\Insider;

/**
 * Provides debug logging, profiling and assertions.
 * 
 * TODO: This is just a skeleton to cover our most basic needs. More coming soon. Refine, optimize, document.
 */
class Insider {
	protected static $initialized = false, $dir, $logCallback;
	
	public static function init($dir) {
		self::$dir = $dir;
		if (!is_dir($dir)) mkdir($dir, 0777, true);
		
		self::$logCallback = function ($event) {
			file_put_contents(self::$dir . '/log.txt', json_encode($event) . "\n", FILE_APPEND);
		};
		
		self::$initialized = true;
		
		// TODO: Temporary, remove.
		self::getLog(__METHOD__)->addInfo('------------------------------------------------------------------------------------------------------------------------------------------------');
		self::getLog(__METHOD__)->addInfo('Log for URL: ' . $_SERVER['REQUEST_URI']);
	}
	
	public static function getLog($symbolName, $code = null) {
		if (!self::$initialized) self::throwNotInitialized();
		return new Log($symbolName, $code, self::$logCallback);
	}
	
// 	public static function getMeter($symbolName, $code = null) {
//		if (!self::$initialized) self::throwNotInitialized();
// 		// TODO
// 	}
	
// 	public static function getAssert($symbolName, $code = null) {
//		if (!self::$initialized) self::throwNotInitialized();
//		// TODO		
//		// Should return a closure used like this $assert($boolExpresions, $optionalFailMessageString_or_failMessageHandler_returning_string);
// 	}

	protected static function throwNotInitialized() {
		throw new \Exception('Initialize Insider before getting a log, meter, or assert.');
	}
}