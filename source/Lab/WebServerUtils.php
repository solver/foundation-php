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
namespace Solver\Lab;

/**
 * Helpers related to using PHP as a standalone web server (typically using the developer server while testing and 
 * debugging).
 */
class WebServerUtils {
	/**
	 * Streams the static file at the given URL path to STDOUT, with a correct "Content-Type" header.
	 * 
	 * @param string $publicRoot
	 * Public root directory (a.k.a. "document root"), no trailing slash.
	 * 
	 * @param string $url
	 * Request URI.
	 * 
	 * @param int|null $expires
	 * Optional. Set to number of seconds, to send caching header that'll keep the resource in cache for that period of
	 * time.
	 * 
	 * @return boolean
	 * True if file was found and served. False if no file was found (and served). 
	 */
	public static function serveStaticFile($publicRoot, $url, $expires = null) {
		$file = $publicRoot . parse_url($url, PHP_URL_PATH);
		
		// Security: disallow traversing the whole file system by using relative paths (..).
		if (preg_match('@(^|/|\\\\)\.\.($|/|\\\\)@', $url)) {
			return false;
		}
				 
		if (file_exists($file) && !is_dir($file)) {
			if (preg_match('/\.(\w+)$/iD', $file, $matches)) {
				$extension = $matches[1];
			} else {
				$extension = null;
			}
			
			$overrides = [
				'css' => 'text/css',
				'js' => 'text/javascript',
			];
			
			if (isset($overrides[$extension])) {
				$mime = $overrides[$extension];
			} else {
				$fi = new \finfo(FILEINFO_MIME_TYPE);
				$mime = $fi->file($file);
			}
			
			if ($expires !== null) {
				\header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', \time() + $expires));
			}
			
			\header('Content-Type: ' . $mime);
			\readfile($file);
			
			return true;
		} else {
			return false;
		}
	}
	
}