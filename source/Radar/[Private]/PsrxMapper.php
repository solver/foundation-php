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
namespace Solver\Radar;

class PsrxMapper {
	public function map($dir, $namespace = null, $callback) {
		$scanDir = function ($dir, $namespace) use (& $scanDir, $callback) {
			$namespace = \trim($namespace, '\\');
			
			try {
				$iterator = new \DirectoryIterator($dir);
			} catch (\RuntimeException $e) {
				throw new \Exception('Invalid or unreadable directory: ' . $dir . '.');
			}
			
			foreach ($iterator as $file) {
				$filename = $file->getFilename();
				
				$isDir = $file->isDir();
				
				// Skip files and dirs with a leading dot (".", "..", ".svn", ".git" etc.)
				if ($filename[0] === '.') continue;
				
				if ($isDir) {
					$scanDir($dir . '/' . $filename, $namespace . '\\' . $filename);
				} else {	
					$pathInfo = \pathinfo($filename);
					
					// Ignore any files not having a PHP extension.
					if (!isset($pathInfo['extension']) || strtolower($pathInfo['extension']) !== 'php') continue;
					
					$symbolPath = $dir . '/' . $filename;
					$symbolName = ($namespace === '' ? '' : $namespace . '\\') . $pathInfo['filename'];
					
					// Dashes are interpreted the same as directory separators: a namespace delimiter.
					$symbolName = str_replace('-', '\\', $symbolName);
					
					// Strip out brackets (and whitespace around them) and anything between them, normalize repeated
					// backslashes, if any.
					$symbolName = \preg_replace('/\s*\[.*?\]\s*/', '', $symbolName);
					$symbolName = \preg_replace('/\\\\+/', '\\', $symbolName);
					
					// Legacy Solver framework "Flow" used extension ".class.php" for classes. "Flow" also had supported
					// arbitrary folders for class placement, without namespacing. To support loading these classes we 
					// filter out ".class" and any namespace when we detect a Flow class. This should be removed when we
					// no longer have Flow code to maintain.
					if (\preg_match('/\.class$/D', $symbolName)) {
						$symbolName = \preg_replace('/(.*\\\\)|\.class$/D', '', $symbolName);
					}
					
					// Using quick heuristics to detect & support legacy classes using underscores (vs. namespaces).
					// TODO: Make this optional?
					$symbolLegacyName = \str_replace('\\', '_', $symbolName);
				
					if (\preg_match('/(class|interface|trait)\s+' . $symbolLegacyName . '\b/si', \file_get_contents($symbolPath))) {
						$symbolName = $symbolLegacyName;
					}
					
					$callback($symbolPath, $symbolName);
				}
			}
		};
		
		$scanDir($dir, $namespace);
	}
}