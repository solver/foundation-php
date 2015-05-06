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

// TODO: Spread out the classes a little once we have a pre-autoloader loading strategy.

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
	
	protected $rootDir, $mappedOnce = false, $composerMap = null, $cacheDir, $symbolDirs = null;
	
	/**
	 * dict...; Maps symbol to files and loaders. 
	 * - simple: dict<symbolName: string, symbolPath: string>; Handled by the built-in loader. Paths are root-relative.
	 * - complex: dict<symbolName: string, config: #ConfigSerialized>; Special symbols handled by pluggable loaders.
	 * - loaders: list<loaderClassName: string>; Index of integer keys to loader class names (used at #Config).
	 * 
	 * #ConfigSerialized: string; A PHP serialized structure, which is of type #Config after deserializing.
	 * 
	 * #Config: tuple...
	 * - symbolPathname: string; Symbol path to the source file.
	 * - loaderIndex: int; Index pointing to a loader class (see key "loaders") to handle this symbol.
	 * - loaderParams: mixed; Arbitrary data (typically typle or dict) passed along to the loader.
	 * 
	 * @var array
	 */
	protected $map;
	
	public function __construct($rootDir, $cacheDir, array $symbolDirs = null) {
		// We use special handling for composer right now. TODO: Move this to the mapping phase to improve performance.
		foreach ($symbolDirs as $path => $handler) {
			if ($handler === 'composer') {
				if ($this->composerMap === null) {
					$this->composerMap = require $rootDir . '/' . $path . '/composer/autoload_classmap.php';
				} else {
					throw new \Exception('You have supplied multiple "composer" directories, you can only have one.');
				}
			}
		}
		
		$this->rootDir = $rootDir;
		$this->cacheDir = $cacheDir;
		$this->symbolDirs = $symbolDirs;
		
		// TRICKY: We need the mute operator to avoid a file_exists check just for first time map cache generation.
		if (($cache = @include $cacheDir . '/cache.php') === false) {
			$this->mapOnce();
		} else {
			$this->map = $cache['map'];
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
		$rootDir = $this->rootDir;
		
		// Composer's map can't re-scan on demand, so native maps are more up-to-date, hence higher priority.
		$mapSimple = $this->map['simple'];
		
		if (isset($mapSimple[$symbolId])) {
			$path = $rootDir . $mapSimple[$symbolId];
			if ($this->symbolDirs) $path = $this->verifyPath($symbolId, $path);			
			if ($path !== null) return $load ? $this->loadPath($rootDir . '/' . $path) : $rootDir . '/' . $path;
		}
		
		// Native map with custom loaders (compiled code etc.).
		$mapComplex = $this->map['complex'];
		
		if (isset($mapComplex[$symbolId])) {
			list($loaderIndex, $loaderParams) = \unserialize($mapComplex[$symbolId]);
			$loaderClass = $this->map['loaders'][$loaderIndex];
			$loaders = & $this->loaders;
			
			if (!isset($loaders[$loaderClass])) {
				$loaders[$loaderClass] = new $loaderClass();
			}
			
			/* @var $loader RadarLoader */
			$loader = $loaders[$loaderClass];
			
			if ($load) {
				$result = $loader->load($symbolName, $loaderParams);
				if ($result[0] === true) return $result;
			} else {
				$result = $loader->find($symbolName, $loaderParams);
				if ($result !== null) return $result;
			}
		}
		
		// Composer classmaps (requires composer to be always run with option -o).
		$composerMap = $this->composerMap;
		
		if ($composerMap && isset($composerMap[$symbolId])) {
			$path = $composerMap[$symbolId];
			if ($this->symbolDirs) $path = $this->verifyPath($symbolId, $path);	
			if ($path !== null) return $load ? $this->loadPath($rootDir . '/' . $path) : $rootDir . '/' . $path;
		}
		
		/*
		 * No match in either map, try a re-map.
		 */
		
		$didMap = $this->mapOnce();
		
		if ($didMap) {
			if (isset($this->map[$symbolId])) {
				$path = $rootDir . $this->map[$symbolId];
				return $load ? $this->loadPath($rootDir . '/' . $path) : $rootDir . '/' . $path;
			} else {
				return $load ? [false, null] : null;
			}
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
			if (!class_exists(PsrxMapper::class, false)) require __DIR__ . '/PsrxMapper.php';
			$mapper = new PsrxMapper();
			
			$badConfig = function ($path, $config) {
				throw new \Exception('Bad config "'. $config .'" for path "'. $path .'".');
			};
			
			$this->map = [
				'simple' => [],
				'complex' => [],
				'loaders' => [],
			];
			
			$import = function ($symbolPathname, $symbolId, $loaderClass = null, $loaderParams = null) {
				var_dump($symbolPathname, $symbolId, $loaderClass, $loaderParams);
				
				// We need canonical paths, as we'll be comparing one with another.
				$symbolPathname = realpath($symbolPathname); 
				
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
					$loaderConfig = serialize([$symbolPathname, $loaderClass, $loaderParams]);
					
					if (isset($map[$symbolId])) {
						// We allow overlapping discovery, but there should be exactly one handler config for one symbol
						// name to load.
						if ($map[$symbold] !== $loaderConfig) {
							$existingLoaderConfig = unserialize($map[$symbolId]);
							throw new \Exception(
								'Symbol "' . $symbolId . '" has been declared with differing loader config, at path "' . $symbolPathname . '" with loader "' . $loaderClass . '" and loader config ' . var_export($loaderConfig, true)
								. ' and at path "' . $existingLoaderConfig[0] . '" with loader "' . $existingLoaderConfig[1] . '" and loader config ' . var_export($existingLoaderConfig[2], true)
							);
						}
					} else {
						$map[$symbolId] = $symbolPathname;
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
				
				$mapper->map($this->rootDir, $path, $config[1], $this->cacheDir . '/compiled', $import);
			}
									
			if (!is_dir($this->cacheDir)) mkdir($this->cacheDir, 0777, true);
			file_put_contents($this->cacheDir . '/map.php', '<?php return ' . var_export($this->map, true) . ';');
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
	
	/**
	 * Used in find() to check a path really exists, when on-demand mapping is enabled (and we expected the cache to
	 * become invalid as developers edit the code).
	 */
	protected function verifyPath($symbolId, $path) {
		$rootDir = $this->rootDir;
		
		// File gone = stale cache. Remap.
		// TODO: We can avoid file_exists() checks when Guardian starts throwing IncludeExceptions.
		if (!\file_exists($rootDir . '/' . $path)) {
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

interface RadarLoader {
	public function find($symbolName, $params = null);
	public function load($symbolName, $params = null);
}

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
			
			var_dump($rules, $dir);
			
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
							if (!is_dir($compiledDir)) mkdir($compiledDir, 0777, true);
							$symbolPathname = $dir . '/' . $filename;
							$symbolName = $this->getSymbolName($symbolPathname, $namespace, $dir, $base);
							$compiledPathname = $compiledDir . '/' .  str_replace('\\', '-', $symbolName) . '.php';
									
							/* @var $compiler PsrxCompiler */
							$compiler = $ruleHandlerOptions;
							file_put_contents($compiledPathname, $compiler->compile($symbolPathname, $symbolName));
							
							$callback($symbolPathname, $symbolName, PsrxLoader::class, [filemtime($symbolPathname), $compiledPathname]);
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

/**
 * An instance of this class is passed to the closure returned by your __config.php files, allowing you to configure
 * the handling of the current directory tree of source files. 
 * 
 * There's one handler assigned for every given file, a handler set at a given directory level overrides handlers for
 * the same files set at a higher level.
 * 
 * Default handlers which are active for a root source directory, and you can override in __config.php:
 * 
 * 1. Files with extension php are sent to the default handler.
 * 2. Files with extension txt, md (Markdown) are ignored.
 * 3. Any other files (that have no handler) cause an exception during the scan.
 * 
 * Default handling for files and directories which can't be overriden:
 * 
 * 1. Files __config.php is a special case, and setting a handler on them has no effect.
 * 2. Any file or directory with a leading dot is ignored, and setting a handler on them has no effect.
 */
class PsrxConfig {
	protected $callback;
	
	public function __construct(\Closure $callback) {
		$this->callback = $callback;	
	}
	
	/**
	 * Sets the handler for a specific file.
	 * 
	 * @param string $name
	 * Filename relative to the current directory. You can specify files in subfolders. Using "." and ".." meta
	 * directory characters is undefined.
	 * 
	 * @return PsrxHandlerConfig
	 */
	public function handleFile($name) {
		return new PsrxHandlerConfig('file', $name, $this->callback);
	}
	
	/**
	 * Sets the handler for a specific directory.
	 * 
	 * @param string $name
	 * Filename relative to the current directory. You can specify arbitrarily deep subdirectories. You can pass "." to
	 * match the directory the file __config.php" is in. Using ".." meta directory character is undefined.
	 * 
	 * @return PsrxHandlerConfig
	 */
	public function handleDir($name) {
		return new PsrxHandlerConfig('dir', $name, $this->callback);		
	}
	
	/**
	 * Sets the handler for a specific file extension.
	 * 
	 * @param string $name
	 * A file extension that begins with a dot. Multiple dots are allowed for composite extensions, like ".tpl.php".
	 * 
	 * Keep in mind that a file name "foo.tpl.php" will only match for extension ".tpl.php" and not ".php".
	 * 
	 * @return PsrxHandlerConfig
	 */
	public function handleFileExt($name) {
		if ($name[0] !== '.') throw new \Exception('Exception names must begin with a dot.');
		return new PsrxHandlerConfig('fileExt', $name, $this->callback);		
	}
}

/**
 * Returned by methods of PsrxConfig to complete the handler configuration.
 */
class PsrxHandlerConfig {
	protected $type, $name, $callback;
	
	public function __construct($type, $name, \Closure $callback) {
		$this->type = $type;
		$this->name = $name;
		$this->callback = $callback;
	}
	
	/**
	 * Causes a file or directory to be ignored (no errors, no scan).
	 */
	public function asIgnored() {
		$this->callback->__invoke($this->type, $this->name, 'ignore', null);		
	}
	
	/**
	 * Declares a file or directory as a standard PHP file. This the default handler for extension ".php". 
	 */
	public function asPhp() {	
		if ($this->type !== 'file' && $this->type !== 'fileExt') {
			throw new \Exception('Handler "php" only applies to files.');
		}
		$this->callback->__invoke($this->type, $this->name, 'php', null);	
	}
	
	/**
	 * Declares a directory (where __config.php is) as a file resource directory.
	 * 
	 * - Files in this dir and subdirectories will be treated as static resources, and won't be scanned.
	 * - This includes file __config.php in a resource directory: it'll be ignored, unless you're declaring a directory
	 * as a resource directory from the __config.php file within that directory (which is supported).
	 * - The location of this directory will be exposed as a symbol in Radar's API (so you can find this directory's 
	 * location by its symbol name). 
	 * - If you compile a simplified source tree all the files and subdirectories will be copied, preserving their 
	 * original layout relative to the resource directory.
	 */
	public function asResourceDir() {	
		if ($this->type !== 'dir') {
			throw new \Exception('Handler "resourceDir" only applies to directories.');
		}
		
		$this->callback->__invoke($this->type, $this->name, 'res', null);
	}
	
	/**
	 * Declares a file as a symbol (class/function) compiled to PHP by the given compiler).
	 * 
	 * @param string $compilerClass
	 * Name of a class implementing interface PsrxCompiler. It'll be created on demand to handle files as necessary.
	 * 
	 * @param array ...$compilerArgs
	 * Optional. Parameters to pass to the compiler's constructor.
	 * 
	 * @throws \Exception
	 */
	public function asCompiledBy($compilerClass, ...$compilerArgs) {	
		if ($this->type !== 'file' && $this->type !== 'fileExt') {
			throw new \Exception('Handler "compiledBy" only applies to files.');
		}
		$this->callback->__invoke($this->type, $this->name, 'compiled', [$compilerClass, $compilerArgs]);	
	}
}

/**
 * Interface compilers should implement.
 */
interface PsrxCompiler {
	/**
	 * Takes path to a source file and symbol to compile (typically a class) and returns source text to the compiled
	 * symbol.
	 * 
	 * @param string $sourcePathname
	 * Full pathname to the source file that should be compiled.
	 * 
	 * @param string $symbolName
	 * Symbol name (i.e. class name) the compiled source should define.
	 * 
	 * @return string $source
	 * Compiled source code to load for this symbol.
	 */
	public function compile($sourcePathname, $symbolName);
}

class PsrxLoader implements RadarLoader {
	/* (non-PHPdoc)
	 * @see \Solver\Radar\RadarLoader::find()
	 */
	public function find($symbolName, $params = null) {
		echo __METHOD__;
	}

	/* (non-PHPdoc)
	 * @see \Solver\Radar\RadarLoader::load()
	 */
	public function load($symbolName, $params = null) {
		echo __METHOD__;
	}
}