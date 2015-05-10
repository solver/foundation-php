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
	public function map($rootDir, $dir, $namespace = null, $compiledDir, $callback) {
		$scanDir = function ($dir, $namespace, $rules) use ($rootDir, & $scanDir, $compiledDir, $callback) {
			$namespace = \trim($namespace, '\\');
			
			if (file_exists($rootDir . '/' . $dir . '/__config.php')) {
				$config = require $rootDir . '/' . $dir . '/__config.php';
				$config(new PsrxConfig(function ($type, $name, $handler, $handlerOptions) use (& $rules) {
					$rules[] = [$type, $name, $handler, $handlerOptions];
				}));
			}
			
			try {
				$iterator = new \DirectoryIterator($rootDir . '/' . $dir);
			} catch (\RuntimeException $e) {
				throw new \Exception('Invalid or unreadable directory: ' . $rootDir . '/' . $dir . '.');
			}
			
			foreach ($iterator as $file) {
				$filename = $file->getFilename();
				$isDir = $file->isDir();
				if (preg_match('/([^\.]*)(\..+)$/', $filename, $matches)) {
					$base = $matches[1];
					$ext = $matches[2];	
				} else {
					$base = $filename;
					$ext = '';
				}
				
				// Skip __config.php & files and dirs with a leading dot (".", "..", ".svn", ".git" etc.)
				if ($filename[0] === '.' || $filename === '__config.php') continue;
				
				$rule = $this->matchRule($isDir, $filename, $base, $ext, $rules);
				
				if ($rule && $rule[0] === 'ignore') continue;
					
				if ($isDir) {
					if (!$rule) {
						$scanDir($dir . '/' . $filename, $namespace . '\\' . $filename, $this->subsetRules($filename, $rules));
					} else {
						$symbolPathname = $dir . '/' . $filename;
						$symbolName = $this->getSymbolName($symbolPathname, $namespace, $dir, $base);
							
						// We have only one directory rule right now (aside from "ignore"), so it's a resource dir.
						$callback($symbolPathname, $symbolName);
					}
				} else {
					if (!$rule) {
						throw new \Exception('No handler found for file "' . $file->getPathname() . '".');
					}
					
					list($ruleType, $ruleName, $ruleHandler, $ruleHandlerOptions) = $rule;
					
					switch ($ruleHandler) {
						case 'php':
							$symbolPathname = $dir . '/' . $filename;
							$symbolName = $this->getSymbolName($symbolPathname, $namespace, $dir, $base);
							
							$callback($symbolPathname, $symbolName);
							break;
						
						case 'compiled':
							$symbolPathname = $dir . '/' . $filename;
							$symbolName = $this->getSymbolName($symbolPathname, $namespace, $dir, $base);
							$callback($symbolPathname, $symbolName, PsrxLoader::class, $ruleHandlerOptions);
							break;
					}
				}
			}
		};
		
		$scanDir($dir, $namespace, [
			['fileExt', '.php', 'php', null],
			['fileExt', '.txt', 'ignore', null],
			['fileExt', '.md', 'ignore', null],
		]);
	}
	
	protected function getSymbolName($pathname, $namespace, $dir, $base) {
		$symbolName = ($namespace === '' ? '' : $namespace . '\\') . $base;
		
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
	
		if (is_file($pathname) && \preg_match('/(class|interface|trait)\s+' . $symbolLegacyName . '\b/si', \file_get_contents($pathname))) {
			$symbolName = $symbolLegacyName;
		}
		
		return $symbolName;
	}
	
	protected function matchRule($isDir, $name, $base, $ext, $rules) {
		// TODO: Specificity support? It would slow us down a tad, but specific filenames should have higher specificity
		// than file extensions, logically.
		
		// We match in reverse as we want the last match to be the one in effect.
		for ($i = count($rules) - 1; $i >= 0; $i--) {
			$rule = $rules[$i];
			list($ruleType, $ruleName) = $rule;
			
			switch ($ruleType) {
				case 'fileExt':
					if (!$isDir && $ext === $ruleName) return $rule;
					break;
					
				case 'file':
					if (!$isDir && $name === $ruleName) return $rule;
					break;
					
				case 'dir':
					if ($isDir && $name === $ruleName) return $rule;
					break;
			}
		}
		
		return null;
	}
	
	protected function subsetRules($localName, $rules) {
		$subsetRules = [];
		
		foreach ($rules as $rule) {
			list($ruleType, $ruleName) = $rule;
			
			if ($ruleType === 'fileExt') {
				$subsetRules[] = $rule;	
			} else {
				if (strpos($ruleName, $localName . '/') === 0) {
					$rule[1] = substr($ruleName, strlen($localName) + 1);
					$subsetRules[] = $rule;
				}
			}
		}
		
		return $subsetRules;
	}
}