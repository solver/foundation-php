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