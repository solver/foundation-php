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
 * A map-based autoloader. Mapping is done on-demand (first run, cache miss, file moved, etc.) and cached. Provides a
 * small set of highly reusable mapping handlers to cover all scenarios.
 */
class Radar {	
	protected static $strategy;
	
	/**
	 * Initializes the autoloader.
	 * 
	 * @param null|string $cacheDir
	 * A directory where Radar can save its $resourceName => $filePath map & other related caches. This location should
	 * be writable to Radar, if you intend to use on-demand remapping.
	 * 
	 * @param array $symbolDirs
	 * dict<filepath: string, specification: #Specification>;
	 * 
	 * Optional. If you pass this parameter, you enable on-demand symbol mapping. This dictionary lists directories and
	 * assigns them to be processed by a given handler. It's recommended to generate your map once and disable on-demand
	 * mapping on production, as some poorly written libraries may issue class_exists with autoloading on classes that
	 * are missing during normal app operation. This could trigger a costly mapping operation (which still won't find
	 * the missing class).
	 * 
	 * #Specification: string; A string in format "handler" or "handler:settings". The settings segment is specific to
	 * each handler, see below. 
	 * 
	 * The following handlers are supported:
	 * 
	 * - Handler "psrx". A PSR-0 and PSR-4 compliant superset (described below). Supports specifying a base namespace
	 * for the given path (PSR-4 compliant), for ex. "psrx:Foo\Bar". 
	 * - Handler "composer". The path should point to Composer's vendor directory. This handler requires that all 
	 * composer updates and installs are done with the "-o" option (generates optimized autoload maps).
	 * 
	 * PSRX:
	 * 
	 * On top of the standard format expected by PSR compliant autoloader, the PSRX handler supports additional
	 * semantics.
	 * 
	 * 1. Directory & file name fragments wrapped in square brackets (and optionally surrounded by whitespace) won't be
	 * counted towards the namespace and name of a class, so you can use it to organize files freely without affecting
	 * the actual class name. All of the following filepaths will map to the same class name ""Foo\Bar":
	 * 
	 * - "Foo/[Controllers]/Bar.php"
	 * - "Foo [Controllers]/Bar.php"
	 * - "Foo/[Module]/[Controllers]/Bar.php"
	 * - "Bar/Bar [Deprecated].php"
	 * - "Bar/[Experimental] Bar.php"
	 * - etc.
	 * 
	 * 2. Aside from nested folders, you can use a dash "-" as a namespace separate to keep your directory tree shallow
	 * when it makes more sense. All of the following filepaths will map to "Foo\Bar\Baz".
	 * 
	 * - "Foo/Bar/Baz.php"
	 * - "Foo/Bar-Baz.php"
	 * - "Foo-Bar/Baz.php"
	 * - "Foo-Bar-Baz.php"
	 * - etc.
	 */
	public static function init($cacheDir, $symbolDirs) {
		if (self::$strategy) throw new \Exception('Radar is already initialized.');
		self::$strategy = new RadarStrategy($cacheDir, $symbolDirs);
		
		// TODO: Bench __autoload vs. spl and replace if needed.
		spl_autoload_register(function ($class) {
			list($found, $result) = self::$strategy->load($class);
			return $found;
		}, true, true);
	}
	
	/**
	 * Returns the filepath for the given symbol id.
	 * 
	 * @param string $symbolName
	 * Fully qualified symbol name (function, class, template id etc.)
	 * 
	 * @return null|string
	 * Full filepath to the symbol (or null if it not found).
	 */
	public static function find($symbolId) {
		if (!self::$strategy) throw new \Exception('Initialize before using find().');
		return self::$strategy->find($symbolId);
	}
	
	/**
	 * Loads the given symbol id (if found).
	 * 
	 * Prefer using this method versus manually including the filepath returned from find(), as Radar may use an
	 * optimized codepath for finding and loading a resource in one go.
	 * 
	 * @param string $symbolName
	 * Fully qualified symbol name (function, class, template id etc.).
	 * 
	 * @return array
	 * tuple...
	 * - found: bool; Whether a symbol with that name was found and loaded.
	 * - result: any; The return result provided by including the resource (if any, otherwise null).
	 * The return result of loading the symbol (if any).
	 */
	public static function load($symbolId) {
		if (!self::$strategy) throw new \Exception('Initialize before using load().');
		return self::$strategy->load($symbolId);
	}
	
	/**
	 * Forces a symbol re-scan.
	 * 
	 * You shouldn't ever need to call this method in most of your apps. Radar will automatically issue re-scans when it
	 * has to in order to keep the map cache current, but it does so up to one time per "PHP request", assuming your
	 * code files don't change *while* PHP is running. If this assumption is wrong (for ex. long-running PHP processes
	 * with "live" codebase updates) you can call this function to update the map.
	 */
	public static function remap() {
		if (!self::$strategy) throw new \Exception('Initialize before using remap().');
		return self::$strategy->remap();
	}
}

/**
 * DO NOT instantiate this class, it's a (likely temporary) implementation detail of Radar. Use Radar directly.
 * 
 * We have this in preparation of eventually having multiple independent strategies, should a use case show up for it.
 */
class RadarStrategy {
	protected $base, $map, $mappedOnce = false, $composerMap = null, $cacheDir, $symbolDirs = null;
	
	public function __construct($cacheDir, array $symbolDirs) {
		// We use special handling for composer right now. TODO: Move this to the mapping phase to improve performance.
		foreach ($symbolDirs as $path => $handler) {
			if ($handler === 'composer') {
				if ($this->composerMap === null) {
					$this->composerMap = require $path . '/composer/autoload_classmap.php';
				} else {
					throw new \Exception('You have supplied multiple "composer" directories, you can only have one.');
				}
			}
		}
		
		$this->cacheDir = $cacheDir;
		$this->symbolDirs = $symbolDirs;
		
		// TRICKY: We need the mute operator to avoid a file_exists check just for first time map cache generation.
		if (($cache = @include $cacheDir . '/cache.php') === false) {
			$this->mapOnce();
		} else {
			$this->base = $cache['base'];
			$this->map = $cache['map'];
		}
	}
	
	public function find($symbolId) {
		/*
		 * Try the maps.
		 * 
		 * Composer can't re-scan if a map is stale (files in "vendor" dir modified), while Radar can, so Radar's own
		 * map has a higher priority.
		 */
		
		$verified = function ($path) use ($symbolId) {
			// The file was moved/renamed/deleted/etc. Stale cache. Remap.
			// TODO: Remove these file_exist() checks once we can rely on throws "include missing" exceptions from Guard.
			if (!\file_exists($path)) {
				// TRICKY: It's not a bug the native map gets remapped even when the Composer map is stale. Developers
				// can have the native map overlap Composer by adding to it select vendor components under development.
				$this->mapOnce();
				return isset($this->map[$symbolId]) ? $this->base . $this->map[$symbolId] : null;
			} else {
				return $path;
			}
		};
		
		$map = $this->map;
		
		if (isset($map[$symbolId])) {
			$path = $verified($this->base . $map[$symbolId]);			
			if ($path !== null) return $path;
		}
		
		$composerMap = $this->composerMap;
		
		if ($composerMap && isset($composerMap[$symbolId])) {
			$path = $verified($composerMap[$symbolId]);			
			if ($path !== null) return $path;
		}
		
		/*
		 * No match in either map, try a re-map.
		 */
		
		$didMap = $this->mapOnce();
		
		if ($didMap) {
			if (isset($map[$symbolId])) {
				return $this->base . $map[$symbolId];
			} else {
				return null;
			}
		} else {
			return null;
		}
	}
	
	public function load($symbolId) {
		$path = $this->find($symbolId);
		if ($path === null) {
			return [false, null];
		} else {
			return [true, require $path];
		}
	}
	
	public function remap() {
		if ($this->symbolDirs === null) {
			throw new \Exception('Cannot remap: symbol locations have not been configured.');
		} else {
			if (!class_exists(__NAMESPACE__ . '\PsrxMapper', false)) require __DIR__ . '/[Internal]/PsrxMapper.php';
			$mapper = new PsrxMapper();
			
			$badConfig = function ($path, $config) {
				throw new \Exception('Bad config "'. $config .'" for path "'. $path .'".');
			};
			
			$this->base = '';
			$this->map = [];
			
			$import = function ($path, $symbolId) {
				// We need canonical paths, as we'll be comparing one with another.
				$path = realpath($path); 
				
				if (isset($this->map[$symbolId])) {
					// We allow overlapping discovery if locations match, but we don't allow the same symbol in two locations.
					if ($this->map[$symbolId] !== $path) {
						throw new \Exception('Symbol "' . $symboId . '" has been declared twice, at "' . $path . '" and "' . $this->map[$symbolId] . '".');
					}
				} else {
					$this->map[$symbolId] = $path;
				}
			};
			
			foreach ($this->symbolDirs as $path => $config) {
				if ($config === 'composer') continue; // We handle this eariler as a special case.
				
				if ($config === '') $badConfig($path, $config);
				$config = explode(':', $config);
				if (count($config) > 2) $badConfig($path, $config);
				if ($config[0] !== 'psrx') $badConfig($path, $config);
				if (!isset($config[1])) $config[1] = null;
				
				$mapper->map($path, $config[1], $import);
			}
			
			// Extracts the common prefix of all paths (if any) to save RAM & I/O from having the cache.
			$this->extractMapBase();
						
			if (!is_dir($this->cacheDir)) mkdir($this->cacheDir, 0777, true);
			file_put_contents($this->cacheDir . '/cache.php', '<?php return ' . var_export(['base' => $this->base, 'map' => $this->map], true) . ';');
		}
	}
	
	protected function mapOnce() {
		if ($this->mappedOnce || $this->symbolDirs === null) {
			return false;
		} else {
			$this->mappedOnce = true;
			$map = $this->remap();
			return true;
		}
	}
	
	protected function extractMapBase() {
		if (!$this->map) return;
		$base = reset($this->map);
		
		foreach ($this->map as $symbolId => $path) {
			while (strpos($path, $base) !== 0 && $base !== '') {
				$base = substr($base, 0, -1);
			}
		}
		
		if ($base !== '')  {
			$this->base = $base;
			$baseLen = strlen($base);
			
			foreach ($this->map as $symbolId => $path) {
				$this->map[$symbolId] = substr($path, $baseLen);
			}
		}
	}
}