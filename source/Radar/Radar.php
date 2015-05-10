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
 * 
 * TODO: Provide a separate tool for generating reusable maps, and for moving all files in the map in a PSR tree (so a 
 * map won't be needed on production). Partial PSR tree is a possible optimization (map first for the special cases 
 * where a class has location-specific behavior, PSR tree as a fallback). A PSR tree of one-liner *.txt files pointing
 * to the real location is another variant. We need tools for all those.
 */
class Radar {	
	protected static $strategy;
	
	/**
	 * Initializes the autoloader.
	 * 
	 * @param string $sourceRootDir
	 * An absolute path to the root directory that all other source directories we'll scan are relative to.
	 * 
	 * @param null|string $cacheRootDir
	 * An absolute path to the directory where Radar can save its $resourceName => $filePath map & other related caches.
	 * This location should be writable to Radar, if you intend to use on-demand remapping. Don't prefix with a slash.
	 * 
	 * @param array $symbolDirs
	 * dict<dirpath: string, specification: #Specification>;
	 * 
	 * Optional. If you pass this parameter, you enable on-demand re-mapping. This dictionary lists directories and
	 * assigns them to be processed by a given handler. It's recommended to generate your map once and disable on-demand
	 * mapping on production, as some poorly written libraries may issue class_exists with autoloading on classes that
	 * are missing during normal app operation. This could trigger a costly re-mapping operation (which still won't find
	 * the missing class). All directories are interpreted as $sourceRootDir-relative. Don't prefix with a slash.
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
	 * when it makes more sense. We chose against dots to avoid collision with composite extensions like ".tpl.php".
	 * All of the following filepaths will map to "Foo\Bar\Baz".
	 * 
	 * - "Foo/Bar/Baz.php"
	 * - "Foo/Bar-Baz.php"
	 * - "Foo-Bar/Baz.php"
	 * - "Foo-Bar-Baz.php"
	 * - etc.
	 * 
	 * 3. Optionally you can put special file "__config.php" in any source directory to configure how Radar should parse
	 * files in the directory, set options etc. The contents of your file should look like this:
	 * 
	 * <code>
	 * <?php return function (Solver\RadarPsrxConfig $cfg) {
	 * 		// Call $cfg API here.
	 * });
	 * </code>
	 * 
	 * For details on the supported options (which are quite powerful), see class PsrxConfig.
	 */
	public static function init($sourceRootDir, $cacheRootDir, array $symbolDirs = null) {
		if (self::$strategy) throw new \Exception('Radar is already initialized.');
		self::$strategy = new RadarStrategy($sourceRootDir, $cacheRootDir, $symbolDirs);
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
	/**
	 * dict<loaderClass: string, loader: PsrxLoader>; Cache storing instantiated loaders. 
	 * 
	 * @var RadarLoader[]
	 */
	protected $loaders;
	
	protected $sourceRootDir, $mappedOnce = false, $composerMap = null, $cacheRootDir, $symbolDirs = null;
	
	/**
	 * dict...; Maps symbol to files and loaders. 
	 * - simple: dict<symbolName: string, symbolPath: string>; Handled by the built-in loader. Paths are root-relative.
	 * - complex: dict<symbolName: string, config: #ConfigSerialized>; Special symbols handled by pluggable loaders.
	 * 
	 * #ConfigSerialized: string; A PHP (JSON) serialized structure, which is of type #Config after deserializing.
	 * 
	 * #Config: tuple...
	 * - symbolPathname: string; Symbol path to the source file.
	 * - loaderClass: string; A loader class used to handle this symbol.
	 * - loaderParams: mixed; Arbitrary data (typically typle or dict) passed along to the loader.
	 * 
	 * @var array
	 */
	protected $map;
	
	public function __construct($sourceRootDir, $cacheRootDir, array $symbolDirs = null) {
		// TODO: Bench __autoload vs. spl and replace if needed.
		spl_autoload_register(function ($class) {
			list($found, $result) = $this->resolve($class, true);
		}, true, true);
		
		// We use special handling for composer right now. TODO: Move this to the mapping phase to improve performance.
		foreach ($symbolDirs as $path => $handler) {
			if ($handler === 'composer') {
				if ($this->composerMap === null) {
					$this->composerMap = require $sourceRootDir . '/' . $path . '/composer/autoload_classmap.php';
				} else {
					throw new \Exception('You have supplied multiple "composer" directories, you can only have one.');
				}
			}
		}
		
		$this->sourceRootDir = $sourceRootDir;
		$this->cacheRootDir = $cacheRootDir;
		$this->symbolDirs = $symbolDirs;
		
		// TRICKY: We need the mute operator to avoid a file_exists check just for first time map cache generation.
		if (($map = @include $cacheRootDir . '/map.php') === false) {
			$this->mapOnce();
		} else {
			$this->map = $map;
		}
	}
	
	public function find($symbolId) {
		return $this->resolve($symbolId, false);
	}
	
	public function load($symbolId) {
		return $this->resolve($symbolId, true);
	}
	
	/**
	 * @param string $symbolId
	 * Symbol to load/find.
	 * 
	 * @param bool $load
	 * True = load the symbol, return load()-compliant result. False = find the symbol, return find()-compliant result.
	 * 
	 * @return mixed
	 */
	protected function resolve($symbolId, $load) {
		$sourceRootDir = $this->sourceRootDir;
		
		// Composer's map can't re-scan on demand, so native maps are more up-to-date, hence higher priority.
		$mapSimple = & $this->map['simple'];
		
		if (isset($mapSimple[$symbolId])) {
			$path = $mapSimple[$symbolId];
			if ($this->symbolDirs) $path = $this->verifyPath($symbolId, $path);			
			if ($path !== null) return $load ? $this->loadPath($sourceRootDir . '/' . $path) : $sourceRootDir . '/' . $path;
		}
		
		// Native map with custom loaders (compiled code etc.).
		$mapComplex = & $this->map['complex'];
		
		if (isset($mapComplex[$symbolId])) {
			list($symbolPath, $loaderClass, $loaderParams) = json_decode($mapComplex[$symbolId], true);
			$loaders = & $this->loaders;
			
			if (!isset($loaders[$loaderClass])) {
				$loaders[$loaderClass] = new $loaderClass($sourceRootDir, $this->cacheRootDir);
			}
			
			/* @var $loader RadarLoader */
			$loader = $loaders[$loaderClass];
			
			if ($load) {
				$result = $loader->load($symbolId, $symbolPath, $loaderParams);
				if ($result[0] === true) return $result;
			} else {
				$result = $loader->find($symbolId, $symbolPath, $loaderParams);
				if ($result !== null) return $result;
			}
		}
		
		// Composer classmaps (requires composer to be always run with option -o).
		$composerMap = &  $this->composerMap;
		
		if ($composerMap && isset($composerMap[$symbolId])) {
			$path = $composerMap[$symbolId];
			if ($this->symbolDirs) $path = $this->verifyPath($symbolId, $path);	
			if ($path !== null) return $load ? $this->loadPath($sourceRootDir . '/' . $path) : $sourceRootDir . '/' . $path;
		}
		
		/*
		 * No match in either map, try a re-map.
		 */
		
		$didMap = $this->mapOnce();
		
		if ($didMap) {
			return $this->resolve($symbolId, $load);
		} else {
			return $load ? [false, null] : null;
		}
	}
	
	protected function loadPath($fullpath) {
		if ($fullpath === null) {
			return [false, null];
		} else {
			return [true, require $fullpath];
		}
	}
	
	public function remap() {
		if ($this->symbolDirs === null) {
			throw new \Exception('Cannot remap: symbol locations have not been configured.');
		} else {
			$mapper = new PsrxMapper();
			
			$badConfig = function ($path, $config) {
				throw new \Exception('Bad config "'. $config .'" for path "'. $path .'".');
			};
			
			$this->map = [
				'simple' => [],
				'complex' => [],
			];
			
			$sourceRootDir = $this->sourceRootDir;
			
			$import = function ($symbolPathname, $symbolId, $loaderClass = null, $loaderParams = null) use ($sourceRootDir) {
				if ($loaderClass === null) {
					$map = & $this->map['simple'];
					
					if (isset($map[$symbolId])) {
						// We allow overlapping discovery (if given folders overlap etc.), but there should be exactly
						// one path for one symbol name to load.
						if ($map[$symbolId] !== $symbolPathname) {
							throw new \Exception('Symbol "' . $symbolId . '" has been declared twice, at path "' . $symbolPathname . '" and "' . $map[$symbolId] . '".');
						}
					} else {
						$map[$symbolId] = $symbolPathname;
					}
				} else {
					$map = & $this->map['complex'];
					
					$loaderConfig = json_encode([$symbolPathname, $loaderClass, $loaderParams], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
					
					if (isset($map[$symbolId])) {
						// We allow overlapping discovery, but there should be exactly one handler config for one symbol
						// name to load.
						if ($map[$symbold] !== $loaderConfig) {
							$existingLoaderConfig = json_decode($map[$symbolId], true);
							throw new \Exception(
								'Symbol "' . $symbolId . '" has been declared with differing loader config, at path "' . $symbolPathname . '" with loader "' . $loaderClass . '" and loader config ' . var_export($loaderConfig, true)
								. ' and at path "' . $existingLoaderConfig[0] . '" with loader "' . $existingLoaderConfig[1] . '" and loader config ' . var_export($existingLoaderConfig[2], true)
							);
						}
					} else {
						$map[$symbolId] = $loaderConfig;
					}
				}
			};
			
			foreach ($this->symbolDirs as $path => $config) {
				if ($config === 'composer') continue; // We handle this eariler as a special case.
				
				if ($config === '') $badConfig($path, $config);
				$config = explode(':', $config);
				if (count($config) > 2) $badConfig($path, $config);
				if ($config[0] !== 'psrx') $badConfig($path, $config);
				if (!isset($config[1])) $config[1] = null;
				
				$mapper->map($this->sourceRootDir, $path, $config[1], $this->cacheRootDir . '/compiled', $import);
			}

			if (!is_dir($this->cacheRootDir)) mkdir($this->cacheRootDir, 0777, true);
			file_put_contents($this->cacheRootDir . '/map.php', '<?php return ' . var_export($this->map, true) . ';');
		}
	}
	
	protected function mapOnce() {
		if ($this->mappedOnce || $this->symbolDirs === null) {
			return false;
		} else {
			// Bootstrapping autoloader for Radar classes.
			spl_autoload_register(function ($class) {
				$pos = strpos($class, 'Solver\Radar\\');
				// Where 13 = string length of "Solver\Radar\".
				if ($pos !== false) require __DIR__ . '/[Private]/' . substr($class, 13) . '.php';
			}, true);
			
			$this->mappedOnce = true;
			$this->remap();
			
			return true;
		}
	}
	
	/**
	 * Used in find() to check a path really exists, when on-demand mapping is enabled (and we expected the cache to
	 * become invalid as developers edit the code).
	 */
	protected function verifyPath($symbolId, $path) {
		// File gone = stale cache. Remap.
		// TODO: We can avoid file_exists() checks when Guardian starts throwing IncludeExceptions.
		if (!\file_exists($this->sourceRootDir . '/' . $path)) {
			// TRICKY: It's not a bug the native map gets remapped even when the Composer map is stale. Developers
			// can have the native map overlap Composer by adding to it select vendor components under development.
			// This can be used to edit files within Composer packages (useful for editing a library in the context
			// of a specific project).
			$this->mapOnce();
			return isset($this->map[$symbolId]) ? $this->map[$symbolId] : null;
		} else {
			return $path;
		}
	}
}