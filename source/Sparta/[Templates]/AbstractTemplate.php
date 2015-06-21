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
namespace Solver\Sparta;

use Solver\Radar\Radar;

/**
 * A simple host for rendering templates. The reason there are separate AbstractTemplate & Template classes is to hide
 * the private members from the templates, in order to avoid a mess (only protected/public methods will be accessible).
 */
abstract class AbstractTemplate {
	/*
 	 * TODO: Once we compile to real classes we should split AbstractTemplate in two:
 	 * 
 	 * 1. AbstractTemplate, the base class for a template with only its protected API methods (for pseudo-functions).
 	 * 2. TemplateEngine, which contains more protected methods that can be overriden, but we don't want to expose
 	 * to templates themselves. 
 	 * 
 	 * Right now we keep some methods protected so they're overridable and some protected because they're template API.
 	 * This has to go. Also, we should consider the template API methods being public, so you can pass $this to a 
 	 * non-template helper and it can use the API to produce output, or define tags etc.
 	 */
	
	/**
	 * We keep track of ob_start() nesting level so when we throw an exception we don't leave output buffer levels
	 * hanging (which causes various odd side effects).
	 */
	protected $obLevel = 0;
	
	/**
	 * A dict container with custom data as passed by the page controller.
	 * 
	 * @var PageModel
	 */
	protected $model;
	
	/**
	 * A log of success/info/warning/error events as passed by the controller.
	 * 
	 * @var PageLog
	 */
	protected $log;
	
	/**
	 * Sets the autoencode format for templates. For a list of the constants and their meanings, see method
	 * getAutoencodeHandler().
	 * 
	 * Negative values are considered same as 0. We're using negative values as a "bypass" flag without losing the
	 * actual mode we're bypassing. The convention is to subtract 0xFFFF, then add it back, when the bypass is removed.
	 *  
	 * @var int
	 */
	protected $autoencodeFormat = 1;
	
	/**
	 * Implements autoescaping, should be passed to ob_start($here, 1);
	 * 
	 * @var \Closure
	 */
	private $autoencodeHandler;
		
	/**
	 * Execution context for the template.
	 * 
	 * @var \Closure
	 */
	private $scope;
	
	/**
	 * @var string
	 */
	private $templateId;
	
	/**
	 * TODO: Unify all stacks, add render/import templates to the stack (will improve content reporting during errors).
	 * 
	 * See method tag().
	 * 
	 * @var array
	 */
	private $tagFuncStack;
	
	/**
	 * TODO: Unify all stacks, add render/import templates to the stack (will improve content reporting during errors).
	 * 
	 * See method tag().
	 * 
	 * @var array
	 */
	private $tagParamStack;
	
	/**
	 * See method tag().
	 * 
	 * @var array
	 */
	private $tagFuncs;
	
	/**
	 * FIXME: If we run multiple views that use the same imports, we'll be pointlessly reloading the same file. This
	 * can be fixed if this list below is static (but this should be fixed together with tags becoming scope-specific,
	 * and also static, so it all works out).
	 * 
	 * Note: when implementing scopes, don't forget a tag should see the tags in its scope of definition. We should
	 * add this to tag define calls somehow.
	 * 
	 * @var array
	 */
	private $renderedTemplateIds = [];
	
	/**
	 * See constructor.
	 *
	 * @var \Closure
	 */
	private $resolver;
	
	/**
	 * @param string $templateId
	 * A template identifier.
	 * 
	 * A template is not a class, but for consistency, it's addressed as if it was a class in a PSR-0 compatible 
	 * directory structure. So template identifiers use backslash for namespace separators just like PHP classes do.
	 * 
	 * If your template id is "Foo\Bar" this will resolve to loading file DOC_ROOT . "/app/templates/Foo/Bar.php".
	 * 
	 * Also, just like classes, you can use directory names wrapped in square brackets purely to group files together
	 * without affecting the template id (see class autoloading).
	 * 
	 * @param null|\Closure $resolver
	 * null | ($templateId: string) => null | string; Takes template id, returns filepath to it (or null if not found).
	 *
	 * This parameter is optional. If not passed, the template id will be resolved via a call to Radar::find().
	 */
	public function __construct($templateId, \Closure $resolver = null) {
		$this->templateId = $templateId;
		$this->resolver = $resolver;
	}
	
	/**
	 * Includes and renders a template. The template has access to the public and protected members of this class, both
	 * by using $this, and by using a local alias that's injected by the system. For example, $this->tag() and $tag()
	 * are equivalent within a template.
	 * 
	 * @param PageModel $model
	 * @param PageLog $log
	 * @return mixed
	 * Anything returned from the template file.
	 */
	public function __invoke(PageModel $model, PageLog $log) {
		try {
			/*
			 * Setup calling scope for this template (and embeded rendered/imported templates).
			 */
			
			$this->model = $model;
			$this->log = $log;
			
			$__localVars__ = $this->getLocalVars();
			
			/* @var $scope \Closure */
			$scope = function ($__path__) use ($__localVars__) {
				extract($__localVars__, EXTR_REFS);
				return require $__path__;
			};
					
			// Hide private properties from the scope (this class is abstract and subclassed by Template, so Template is
			// the topmost class possible to instantiate). If you extend Template and override the methods, don't forget
			// to rebind the scope to the your class.
			$scope = $scope->bindTo($this, get_class($this));
			$this->scope = $scope;
					
			$this->autoencodeHandler = $this->getAutoencodeHandler();
			
			\ob_start($this->autoencodeHandler, 1);
			$this->obLevel += 1;
			$result = $this->render($this->templateId);
			\ob_end_flush();
			$this->obLevel -= 1;
			
			return $result;
		} finally {
			// During normal exit $this->obLevel is 0, so this is only for abnormal conditions.
			while ($this->obLevel--) ob_end_clean();
		}
	}	
	
	/**
	 * Renders another template inline within this template.
	 * 
	 * @param string $templateId
	 * Id of the template to render. Same rules as a $templateId passed to the constructor (see the constructor for
	 * details).
	 */
	protected function render($templateId) {
		// A leading backslash is accepted as some people will write one, but template ids are always absolute anyway.
		$templateId = \ltrim($templateId, '\\');
		
		if ($this->resolver) {
			$path = $this->resolver->__invoke($templateId);
		} else {
			$path = Radar::find($templateId);
		}
		
		if ($path === null) {
			$this->abortWithError('Template "' . $templateId . '" not found.');
		}
		
		$tagFuncStackDepth = \count($this->tagFuncStack);
		$tagParamStackDepth = \count($this->tagParamStack);
		$scope = $this->scope;
		
		$result = $scope($path);
		
		if ($tagFuncStackDepth < \count($this->tagFuncStack)) {
			list($func, $params) = \array_pop($this->tagFuncStack);
			$this->abortWithError('Tag function "' . $func . '" was opened but never closed, in templateId ' . $templateId . '.');
		}
		
		if ($tagFuncStackDepth < \count($this->tagParamStack)) {
			list($func, $params) = \array_pop($this->tagFuncStack);
			$param = \array_pop($this->tagFuncStack);
			$this->abortWithError('Parameter "@' . $param . '" for tag function "' . $func . '" was opened but never closed, in templateId ' . $templateId . '.');
		}
		
		// Used if the same id is import()-ed a second time.
		$this->renderedTemplateIds[$templateId] = $result;

		return $result;
	}
	
	/**
	 * Same as render(), with these differences:
	 * 
	 * - If this templateId was already imported (or rendered) before, it won't be imported again (think require_once).
	 * - Any text output generated while loading the file (via echo or otherwise) will be ignored.
	 * 
	 * The latter is handy for importing templates that contain only tag definitions (functions) for re-use. Whitespace 
	 * outside your functions will not be ignored, and any text along with it (say, HTML comments). You can freely
	 * annotate your code in whatever format you prefer, for ex.:
	 * 
	 * <code>
	 * This text won't be sent to the browser.
	 * 
	 * <? tag('foo', function () { ?>
	 * 		This text, will be rendered if you call tag('foo/') *after* the import.
	 * <? }) ?>
	 * </code>
	 * 
	 * @param string $templateId
	 * A template identifier (for details on template identifiers, see render()).
	 * 
	 * @return mixed
	 * Anything returned from the template file (if the template was rendered/imported before, you'll get the return
	 * value from that first render/import call).
	 */
	protected function import($templateId) {
		if (isset($this->renderedTemplateIds[$templateId])) return;
		
		\ob_start();
		$this->obLevel += 1;
		
		$result = $this->render($templateId);
		
		\ob_end_clean();
		$this->obLevel -= 1;
		
		return $result;
	}
	
	/**
	 * Sets the auto-escape format for templates (by default HTML).
	 * 
	 * @param boolean|string $format
	 * Autoescape format string, or false not to autoencode.
	 */
	protected function autoencode($format) {
		/*
		 * TODO: The input arguments here (mixing boolean and strings) are undesirable. We also allow one template to 
	 	 * change the autoescape for another, instead the changes should be scoped (begin...end autoescape context) and 
	 	 * well contained to the template that wants them (no side-effects "leaking" outside their context).
		 */
		
		static $labels = [
			'html' => 1,
			'json' => 2,
		];
		
		if ($format === false) {
			$this->autoencodeFormat = 0;
		} else {
			if (!isset($labels[$format])) $this->abortWithError('Unknown encoding format "' . $format . '".');
			$this->autoencodeFormat = $labels[$format];
		}
	}
	
	/**
	 * Returns the given value encoded as an HTML string literal.
	 * 
	 * @param mixed $value
	 * @return string
	 */
	protected function toHtml($value) {
		return $value === null ? '' : \htmlspecialchars($value, \ENT_QUOTES, 'UTF-8');
	}
	
	/**
	 * Outputs the given value encoded as JSON.
	 * 
	 * @param mixed $value
	 * @return string
	 */
	protected function toJson($value) {
		return \json_encode($value, \JSON_UNESCAPED_UNICODE);
	}
	
	/**
	 * Outputs the given value as-is, bypassing the current autoencode format.
	 * 
	 * @param mixed $value
	 */
	protected function echoRaw($value) {
		$this->autoencodeFormat -= 0xFFFF;
		echo $value;
		$this->autoencodeFormat += 0xFFFF;
	}
	
	/**
	 * Outputs the given value encoded as an HTML string literal, bypassing the current autoencode format.
	 * 
	 * @param mixed $value
	 */
	protected function echoHtml($value) {
		$this->autoencodeFormat -= 0xFFFF;
		echo $value === null ? '' : \htmlspecialchars($buffer, \ENT_QUOTES, 'UTF-8');
		$this->autoencodeFormat += 0xFFFF;
	}
	
	/**
	 * Outputs the given value encoded as JSON, bypassing the current autoencode format.
	 * 
	 * @param mixed $value
	 */
	protected function echoJson($value) {
		$this->autoencodeFormat -= 0xFFFF;
		echo \json_encode($value, \JSON_UNESCAPED_UNICODE);
		$this->autoencodeFormat += 0xFFFF;
	}

	/** 
	 * "Tag" is a light system for registering functions as reusable blocks of content, and then calling them in a 
	 * format well suited for templates. There are few benefits over plain function calls:
	 * 
	 * - You set parameters by name (easy to extend & add parameters for big templates, as parameter order doesn't
	 * matter).
	 * - You can set parameters from content, i.e. "content parameters" using the "@" syntax (see below), and autoencode
	 * will function properly while capturing parameter content (if you try to use ob_* yourself, autoencode won't
	 * work correctly).
	 * - The system is designed to look like HTML tags (as much as possible), hence the name, in order to be intuitive
	 * to front-end developers.
	 * 
	 * In the examples below, the shortcut "tag()" is used (generated for templates by the render/import methods), which
	 * for a template is the same as calling "$this->tag()".
	 *
	 * An example of defining a template:
	 * <code>
	 * <? tag('layout', function ($title = '', $head = '', $body = '') { ?>
	 *		<html>
	 *			<head>
	 *				<title><?= $title ?></title>
	 *				<? echo_raw($head) ?>
	 *			</head>
	 *			<body>
	 *				<? echo_raw($body) ?>
	 *			</body>
	 *		</html>
	 * <? }) // layout ?>
	 * </code>
	 *
	 * An example usage of the above template. You can specify a parameter inline (title), separately(bodyClass) or from
	 * content (head, body):
	 * <code>
	 * <? tag('layout', ['title' => 'Hi, world']) ?>
	 *		<? tag('@bodyClass/', 'css-class-name') ?>
	 *
	 *		<? tag('@head') ?>
	 *			<style>
	 *				body {color: red}
	 *			</style>
	 *		<? tag('/@head') ?>
	 *	
	 *		<? tag('@body') ?>
	 *			<p>Hi, world!</p>
	 *		<? tag('/@body') ?>
	 *	
	 * <? tag('/layout') ?>
	 * </code>
	 *
	 * A shorter way to invoke a template with one content parameter:
	 * <code>
	 * <? tag('layout@body') ?>
	 * 		<p>Hi, world!</p>
	 * <? tag('/layout@body') ?>
	 * </code> 
	 * 
	 * A shorter way to invoke a template without any content parameters (so you can skip the closing tag):
	 * <code>
	 * <? tag('layout/') ?>
	 * <code>
	 * 
	 * At the moment you're closing a "tag" you're calling the function. So that's when you can grb the return result,
	 * if any (having a return result isn't typical for a template function and looks a bit odd; use sparingly):
	 * <code>
	 * <? tag('layout', [...]) ?>
	 *	
	 *		<? tag('@head') ?>
	 *			...
	 *		<? tag('/@head') ?>
	 *	
	 *		<? tag('@body') ?>
	 *			...
	 *		<? tag('/@body') ?>
	 *	
	 * <? $result = tag('/layout') ?>
	 * </code>
	 * 
	 * It works with the short syntaxes, as well: 
	 * <code>
	 * <? $result = tag('layout/', [...]) ?>
	 * </code>
	 */ 
	protected function tag($name, $params = null) {
		// TODO: Make this scoped (like say Java/C# imports) to the file calling $import(), allow up-scope imports if explicitly specified (i.e. "get the imports this import is including").
		$funcStack = & $this->tagFuncStack;
		$paramStack = & $this->tagParamStack;
		$funcs = & $this->tagFuncs;
		$result = null;
		
		/*
		 * Interpret arguments.
		 */
				
		$tagParamCount = \func_num_args();
		
		// Register a new template function.
		if ($params instanceof \Closure) {
			if (isset($funcs[$name])) $this->abortWithError('Tag function named "' . $name . '" was already declared.');
			$funcs[$name] = $params;
			return;
		}
		
		// Self-closing tag <foo/>.
		if ($name[\strlen($name) - 1] === '/') {
			$isSelfClosing = true;
			$name = \substr($name, 0, -1);
		} else {
			$isSelfClosing = false;
		}
		
		// Closing tag </foo> vs. opening tag <foo>.
		if ($name[0] === '/') {
			$isOpening = false; 
			$name = \substr($name, 1);
		} else {
			$isOpening = true;
		}
		
		// Function parameter tag <@foo> vs. function tag <foo>.
		if ($name[0] === '@') {
			$isParam = true;
			$name = \substr($name, 1);
		} else {
			$isParam = false;
		}
		
		// Shortcut <function@param> detection.
		if (\strpos($name, '@') !== false) {
			$name = \explode('@', $name);
			$shortcutParam = $name[1];
			$name = $name[0];
		} else {
			$shortcutParam = null;
		}
		
		/*
		 * Main logic.
		 */
				
		if ($shortcutParam && !$isOpening) {
			$this->tag('/@' . $shortcutParam);
		}
		
		if ($isParam) {
			$lastFuncStackId = \count($funcStack) - 1;
				
			if ($isOpening) {
				if ($lastFuncStackId < 0) {
					$this->abortWithError('Opening parameter "@' . $name . '" without being in a tag function context.');
				}
			
				if (isset($funcStack[$lastFuncStackId][1][$name])) {
					$this->abortWithError('Duplicate declaration of parameter "@' . $name . '" while calling tag function "'. $funcStack[$lastFuncStackId][0] .'".');
				}
				
				if ($tagParamCount > 1) {
					// Param set directly.
					if ($isSelfClosing) {
						$funcStack[$lastFuncStackId][1][$name] = $params;
					} else {
						$this->abortWithError('When specifying a parameter value as a second parameter of $tag(), the parameter tag should be self-closing.');
					}
				} else {
					// Param open.
					$paramStack[] = $name;
					\ob_start(); // For buffering to a string.
					\ob_start($this->autoencodeHandler, 1); // For autoescaping.
					$this->obLevel += 2;
				}
			} else { 
				// Param close.
				$nameOnStack = \array_pop($paramStack);
				
				if ($name !== null && $nameOnStack !== $name) {
					$this->abortWithError('Parameter end mismatch: closing "' . $name . '", expecting to close "' . $nameOnStack . '".');
				}
				
				\ob_end_flush(); // Closing autoencode handler.
				$funcStack[$lastFuncStackId][1][$name] = \ob_get_clean(); // Grab param content from buffer.
				$this->obLevel -= 2;
			}
		} else {
			if ($isOpening) { 
				// Function call open.
				if (!isset($funcs[$name])) $this->abortWithError('Undefined template function "' . $name . '".');
				$funcStack[] = [$name, $params === null ? [] : $params];
			} else { 
				// Function call close.
				$func = \array_pop($funcStack);
		
				if ($name !== null && $func[0] !== $name) {
					$this->abortWithError('Template function end mismatch: closing "' . $name . '", expecting to close "' . $func[0] . '".');
				}
				
				$funcName = $func[0];
				$funcImpl = $funcs[$funcName];
				$funcParamDict = $func[1];
				$params = $this->tagGetFunctionParams($funcName, $funcImpl, $funcParamDict);
				$result = $funcImpl(...$params);
			}
		}
		
		if ($shortcutParam && $isOpening) {
			$this->tag('@' . $shortcutParam);
		}
		
		if ($isSelfClosing && !$isParam) {
			$result = $this->tag('/' . $name);
		}
		
		return $result;
	}
	
	private function tagGetFunctionParams($funcName, $funcImpl, $funcParamDict) {
		$reflFunc = new \ReflectionFunction($funcImpl);
		$params = [];
		
		/* @var $reflParam \ReflectionParameter */
		foreach ($reflFunc->getParameters() as $reflParam) {
			$paramName = $reflParam->getName();
			
			if (\key_exists($paramName, $funcParamDict)) {
				$params[] = $funcParamDict[$paramName];
				unset($funcParamDict[$paramName]);
			} else {
				if ($reflParam->isOptional()) {
					$params[] = $reflParam->getDefaultValue();
				} else {
					$paramStack = & $this->tagParamStack;
					$paramStackLastIndex = \count($paramStack) - 1;
					
					if ($paramStackLastIndex > -1 && $paramStack[$paramStackLastIndex] === $paramName) {
						$this->abortWithError('Required parameter "@' . $paramName . '" for tag function "' . $funcName . '" was opened but never closed.');
					} else {
						$this->abortWithError('Parameter "@' . $paramName . '" for tag function "' . $funcName . '" is missing.');
					}
				}
			}
		}
		
		if ($funcParamDict) {
			$this->abortWithError('One or more unknown parameters were passed to tag function "' . $funcName . '": "@' . \implode('", "@', \array_keys($funcParamDict)) . '".');
		}
		
		return $params;
	}
	
	/**
	 * Return a list of local variables to be extracted into the scope of the template that'll run (optionally by
	 * reference). Override in child classes to change these variables.
	 */
	protected function getLocalVars() {
		return [
			'model' => & $this->model,
			'log' => & $this->log,
		];
	}
	
	protected function getAutoencodeHandler() {
		$format = & $this->autoencodeFormat;
		
		return function ($buffer) use (& $format) {
			// Filter "raw" - no encoding.
			if ($format <= 0) return $buffer;
			 
			switch ($format) {
				case 1: // Filter "html" text node encoding.
					return \htmlspecialchars($buffer, \ENT_QUOTES, 'UTF-8');
				case 2: // Filter "json" primitive encoding.
					return \json_encode($value, \JSON_UNESCAPED_UNICODE);
				default:
					$this->abortWithError('Unknown autoencode format code ' . $format . '.');
			}
		};
	}
	
	protected function abortWithError($message) {
		throw new \Exception($message);
	}
}