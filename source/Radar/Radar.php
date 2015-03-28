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

/**
 * Scans given directories using PSR-0 & PSR-4 compatible superset of features, produces a map (cached) and uses the
 * map to resolve class/function/etc. locations based on their name.
 * 
 * Typical usage is for defining autoloaders:
 * 
 * <code>
 * $radar = new Radar(...); 
 * 
 * // Standard style autoloader (slower, allows chaining).
 * spl_autoload_register(function ($class) use ($radar) {
 * 	   $path = $radar->find($class);
 * 	   if ($path !== false) require $path;
 * });
 * 
 * // Alternative (old) style autoloader (faster, no chaining, uses a global).
 * function __autoload($class) {
 * 	   global $radar;
 *     $path = $radar->find($class);
 *     if ($path) require $path;
 * }
 * </code>
 */
class Radar {
	protected $cacheDir, $symbolDirs, $nativeMap, $composerMap;
	
	/**
	 * @param array $symbolDirs
	 * A dict of locations to scan for classes, functions, templates and any other loadable symbols, in this format:
	 * 
	 * <code>
	 * [
	 * 		"path/to/files" => "Optional\Namespace\Prefix",
	 * 		...,
	 * 		...
	 * ]
	 * </code>
	 * 
	 * Paths are considered relative to APP_ROOT.
	 *  
	 * For PSR-0 formatted directories, you can have an empty string as the namespace prefix.
	 * 
	 * An additional feature: directory & file name fragments wrapped in square brackets (and optionally surrounded by 
	 * whitespace) won't be counted towards the namespace and name of a class, so you can use it to organize files
	 * freely without affecting the actual class name.
	 * 
	 * All of the following filepaths will map to the same class name "Foo\BarController":
	 * 
	 * - "Foo/[Controllers]/BarController.php"
	 * - "Foo [Controllers]/BarController.php"
	 * - "Foo/[Module]/[Controllers]/BarController.php"
	 * - "Bar/BarController [Deprecated].php"
	 * - "Bar/[Experimental] BarController.php"
	 * - etc.
	 * 
	 * @param null|string $cacheDir
	 * A directory where Radar can save its $symbolName => $filePath maps. This location should be writable to Radar.
	 * 
	 * @param null|string $composerVendorDir
	 * If you specify this, Radar will include all composer classes into its lookups. Note you MUST use the -o option
	 * in composer when installing/updating dependencies, as Radar uses the autoload map produced by this option. If you
	 * don't need this feature, pass null.
	 */
	public function __construct($cacheDir, array $symbolDirs, $composerVendorDir = null) {
		/*
		 * Initialize autoloader state.
		 */
		$this->symbolDirs = $symbolDirs;
		
		// Some packages still use this feature (odd), and since we replace Composer's autoloader, we should support it.
//		UNDER CONSIDERATION. The always-loaded files (like from Swift) are horribly slow and it's such a bad idea to 
//		always load them.
//		
// 		$autoloadFiles = \APP_ROOT . '/vendor/composer/autoload_files.php';
// 		if (file_exists($autoloadFiles)) foreach (require $autoloadFiles as $file) {
// 			require $file;
// 		}
		 
		$this->composerMap = $composerVendorDir ? $composerVendorDir . '/composer/autoload_classmap.php' : null;
		
		// TRICKY: We need the mute operator to avoid a file_exists check just for first time map cache generation.
		if (($this->nativeMap = @include $cacheDir . '/map.php') === false) {
			$this->nativeMap = $this->map();
		}
	}
	
	/**
	 * Resolves class names & template ids to an absolute file path.
	 * 
	 * @param string $symbolId
	 * A full class name, function, template id etc.
	 * 
	 * @return string|false
	 * Full path to the resolved file. False if the file was not found.
	 */
	public function find($symbolId) {		
		/*
		 * Try the maps.
		 * 
		 * Note: Composer has no automatic rescan on stale map, we do, so always query native 1st (above), Composer 2nd.
		 * We also no longer trust Composer's map to be up-to-date (it isn't when a module in the vendor dir is being
		 * worked on) hence the file check applies to Composer too.
		 */
		
		$getVerified = function ($path) use ($symbolId) {
			// TODO: This file_exists() check can go once the error-to-exception mapper is ported. That'll speed things
			// up a notch.
			if (!\file_exists($path)) { 
				// The file was moved/renamed/deleted/etc. Stale cache. Remap.
				// TRICKY: It's not a bug the native map gets remapped even when the Composer map is stale. Developers
				// can have the native map overlap Composer by adding to it select /vendor components under development.
				$this->nativeMap = self::map();
				
				if (isset($this->nativeMap[$symbolId])) {
					return $this->nativeMap[$symbolId];
				} else {
					return false;
				}
			} else {
				return $path;
			}
		};
		
		$map = $this->nativeMap;
		
		if (isset($map[$symbolId])) {
			$path = $getVerified(\APP_ROOT . '/' . $map[$symbolId]);			
			if ($path !== false) return $path;
		}
		
		$map = $this->composerMap;
		
		if (isset($map[$symbolId])) {
			$path = $getVerified($map[$symbolId]);			
			if ($path !== false) return $path;
		}
		
		/*
		 * No match in either map, try a re-map.
		 */
		
		$newMap = self::map();
		
		// TRICKY: Some (not very well written) third party code probes for class existence by invoking the autoloader
		// on a non-existing class. This causes a remap, but doesn't mean the script will end with a fatal error at this
		// point. So we should take care not to overwrite the native map (with false) if map() is running for the second
		// time in this script. To work around the performance implications of rescanning on every run, production code
		// should not use automatic remapping, but be deployed with a stable pre-generated map. Alternatively, code 
		// shouldn't call class_exists without disabling autoloader look-ups, if the class is expected not to exist 
		// during normal script operation (the saner alternative).
		if ($newMap !== false) {
			$map = $this->nativeMap = $newMap;
			
			if (isset($map[$symbolId])) {
				return \APP_ROOT . '/' . $map[$symbolId];
			} else {
				return false;
			}
		} else {
			return false;
		}
		
	}
	
	protected function map() {
		// There's no point in mapping more than once per request. So we refuse a second map run & let the app fail with
		// the relevant "symbol not found" error.
		static $didMap = false;
		if ($didMap) return false;
		$didMap = true;
		
		$map = [];
		
		$scanDir = function ($dir, $ns) use (& $scanDir, & $map) {
			$ns = \trim($ns, '\\');
			
			try {
				$iterator = new \DirectoryIterator(\APP_ROOT . '/'. $dir);
			} catch (\RuntimeException $e) {
				throw new \Exception('Invalid or unreadable directory: ' . $dir . '.');
			}
			
			foreach ($iterator as $file) {
				$filename = $file->getFilename();
				
				$isDir = $file->isDir();
				
				// Skip files and dirs with a leading dot (".", "..", ".svn", ".git" etc.)
				if ($filename[0] === '.') continue;
				
				if ($isDir) {
					$scanDir($dir . '/' . $filename, $ns . '\\' . $filename, $map);
				} else {	
					$pathInfo = \pathinfo($filename);
					
					// Ignore any files not having a PHP extension.
					if (!isset($pathInfo['extension']) || $pathInfo['extension'] !== 'php') continue;
					
					$symbolPath = $dir . '/' . $filename;
					$symbolName = ($ns === '' ? '' : $ns . '\\') . $pathInfo['filename'];
					
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
					$symbolLegacyName = \str_replace('\\', '_', $symbolName);
				
					if (\preg_match('/(class|interface|trait)\s+' . $symbolLegacyName . '\b/si', \file_get_contents(\APP_ROOT . '/' . $symbolPath))) {
						$symbolName = $symbolLegacyName;
					}
					
					if (isset($map[$symbolName])) {
						throw new \Exception('Duplicate declarations for symbol ' . $symbolName . ' at file "' . $map[$symbolName] . '" and file "' . $symbolPath . '".');
					}
					
					$map[$symbolName] = $symbolPath;
				}
			}
		};
		
		foreach ($this->symbolDirs as $dir => $ns) {
			$scanDir($dir, $ns);
		}
		
		if (is_dir($this->cacheDir)) mkdir($this->cacheDir, 0777, true);
		\file_put_contents($this->cacheDir . '/map.php', '<?php return ' . \var_export($map, true) . ';');
		return $map;
	}
}