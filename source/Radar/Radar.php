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
	 * @param null|string $cacheDir
	 * A directory where Radar can save its $resourceName => $filePath map & other related caches. This location should
	 * be writable to Radar, if you intend to use on-demand remapping.
	 * 
	 * @param array $symbolDirs
	 * dict<filepath: string, specification: #Specification>;
	 * 
	 * Optional. If you pass this parameter, you enable on-demand re-mapping. This dictionary lists directories and
	 * assigns them to be processed by a given handler. It's recommended to generate your map once and disable on-demand
	 * mapping on production, as some poorly written libraries may issue class_exists with autoloading on classes that
	 * are missing during normal app operation. This could trigger a costly re-mapping operation (which still won't find
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
	 * 2. Aside from nested folders, you can use a dot "." as a namespace separate to keep your directory tree shallow
	 * when it makes more sense. All of the following filepaths will map to "Foo\Bar\Baz".
	 * 
	 * - "Foo/Bar/Baz.php"
	 * - "Foo/Bar.Baz.php"
	 * - "Foo.Bar/Baz.php"
	 * - "Foo.Bar.Baz.php"
	 * - etc.
	 */
	public static function init($cacheDir, array $symbolDirs = null) {
		if (self::$strategy) throw new \Exception('Radar is already initialized.');
		if (!class_exists(RadarStrategy::class, false)) require __DIR__ . '\[Private]\RadarStrategy.php';
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