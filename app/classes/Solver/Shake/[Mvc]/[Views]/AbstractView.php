<?php
namespace Solver\Shake;

/**
 * A simple host for rendering templates. The reason there are separate AbstractView & View classes is to hide the  
 * private members from the templates, in order to avoid a mess (only protected/public methods will be accessible).
 * 
 * @author Stan Vass
 * @copyright © 2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
abstract class AbstractView {
	/**
	 * The router which invoked the active controller instance.
	 * 
	 * @var \Solver\Shake\Router
	 */
	protected $router;
		
	/**
	 * The controller which invoked this view instance.
	 * 
	 * @var \Solver\Shake\Controller
	 */
	protected $controller;
	
	/**
	 * A dict of custom data as passed by the controller wrapped in a DataBox instance for convenient data access.
	 * 
	 * @var \Solver\Shake\DataBox
	 */
	protected $data;
	
	/**
	 * A log of success/info/warning/error events as passed by the controller.
	 * 
	 * @var \Solver\Shake\ControlledLog
	 */
	protected $log;
	
	/**
	 * This is where, by convention, imported templates and the main view template can share data (preferably using the 
	 * shortcut notation "$shared" instead of the also valid "$this->shared"). 
	 * 
	 * This is needed, because while every template has access to the view instance members, any other local variables
	 * a template creates are scoped to that template (you can't share any custom local variables between imports, for 
	 * example). This is intentional, in order to avoid hard to debug variable collisions between complex templates.
	 * 
	 * Use this as a last resort. Better ways to share data between templates:
	 * 
	 * - Definitions in the $tag() system (see method tag()), which are stored at the view instance level.
	 * - Template return results.
	 * 
	 * @var array
	 */
	protected $shared = [];
		
	/**
	 * See method getShortcuts().
	 * 
	 * @var array
	 */
	private $shortcuts;
	
	/**
	 * See method render().
	 * 
	 * @var mixed
	 */
	private $temp;
	
	/**
	 * See method tag().
	 * 
	 * @var array
	 */
	private $tagFuncStack;
	
	/**
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
	
	public function __construct(Router $router, Controller $controller, array $data, ControllerLog $log) {
		$this->router = $router;
		$this->controller = $controller;
		$this->data = new DataBox($data);
		$this->log = $log;
	}
	
	/**
	 * Includes and renders a template. The template has access to the public and protected members of this class, both
	 * by using $this, and by using a local alias that's injected by the system. For example, $this->tag() and $tag()
	 * are equivalent within a template.
	 * 
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
	 * @return mixed
	 * Anything returned from the template file.
	 */
	public function render($templateId) {
		// A leading backslash is accepted as some people will write one, but template ids are always absolute anyway.
		$templateId = \ltrim($templateId, '\\');
		
		if (($path = Core::resolve($templateId)) === false) {
			throw new \Exception('View template ' . $templateId . ' not found.');
		}
		
		// We don't want any params to pollute or collide with the local var space of the template included here.
		$this->temp = [
			'path' => $path,
			'templateId' => $templateId,
		];
		unset($templateId, $path);
		
		// Any protected/public members (except the constructor) will be accessible without $this within a template.
		\extract($this->getShortcuts(), \EXTR_REFS);
		
		$result = require $this->temp['path'];
		$this->renderedTemplateIds[$this->temp['templateId']] = $result; // Used if the same id is import()-ed a second time.
		
		$this->temp = null;
		
		return $result;
	}	
	
	/**
	 * Same as render(), with these differences:
	 * 
	 * - If this templateId was already imported (or rendered) before, it won't be imported again (think require_once).
	 * - Any text output generated while loading the file (via echo or otherwise) will be ignored.
	 * 
	 * The latter is handy for importing templates that contain only definitions (functions) for re-use. Whitespace 
	 * outside your functions will not be ignored, and any text along with it (say, HTML comments). You can freely
	 * annotate your code in whatever format you prefer, for ex.:
	 * 
	 * <code>
	 * <!-- This text won't be sent to the browser. -->
	 * 
	 * <? $definition = function () { ?>
	 * 		This text, however, will be sent if you call $definition() *after* the import.
	 * <? } ?>
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
		$result = $this->render($templateId);
		\ob_end_clean();
		return $result;
	}
	

	/**
	 * "Tag" is a light system for registering functions as reusable blocks of content, and then calling them in a format
	 * well suited for templates. It mimics template parser systems, without the parser overhead. There are few benefits
	 * over plain function calls:
	 * 
	 * - You set parameters by name (easy to extend & add parameters for big templates, as parameter order doesn't
	 * matter).
	 * - You can set parameters from content, i.e. "content parameters" using the "@" syntax (see below).
	 * - The system is designed to look like HTML tags (as much as possible), hence the name, in order to be intuitive
	 * to front-end developers.
	 * 
	 * In the examples below, the shortcut "$tag()" is used (generated for templates by the render/import methods), but
	 * using "$this->tag()" has equivalent semantics.
	 *
	 * An example of defining a template:
	 * <code>
	 * <? $tag('layout', function ($title = '', $head = '', $body = '') { ?>
	 *		<html>
	 *			<head>
	 *				<title><?= $esc($title) ?></title>
	 *				<?= $head ?>
	 *			</head>
	 *			<body>
	 *				<?= $body ?>
	 *			</body>
	 *		</html>
	 * <? }) // layout ?>
	 * </code>
	 *
	 * An example usage of the above template. You can specify a parameter inline (title), separately(bodyClass) or from
	 * content (head, body):
	 * <code>
	 * <? $tag('layout', ['title' => 'Hi, world']) ?>
	 *		<? $tag('@bodyClass/', 'css-class-name') ?>
	 *
	 *		<? $tag('@head') ?>
	 *			<style>
	 *				body {color: red}
	 *			</style>
	 *		<? $tag('/@head') ?>
	 *	
	 *		<? $tag('@body') ?>
	 *			<p>Hi, world!</p>
	 *		<? $tag('/@body') ?>
	 *	
	 * <? $tag('/layout') ?>
	 * </code>
	 *
	 * A shorter way to invoke a template with one content parameter:
	 * <code>
	 * <? $tag('layout@body') ?>
	 * 		<p>Hi, world!</p>
	 * <? $tag('/layout@body') ?>
	 * </code> 
	 * 
	 * A shorter way to invoke a template without any content parameters (so you can skip the closing tag):
	 * <code>
	 * <? $tag('layout/') ?>
	 * <code>
	 * 
	 * At the moment you're closing a "tag" you're calling the function. So that's when you can grb the return result,
	 * if any (having a return result isn't typical for a template function and looks a bit odd; use sparingly):
	 * <code>
	 * <? $tag('layout', [...]) ?>
	 *	
	 *		<? $tag('@head') ?>
	 *			...
	 *		<? $tag('/@head') ?>
	 *	
	 *		<? $tag('@body') ?>
	 *			...
	 *		<? $tag('/@body') ?>
	 *	
	 * <? $result = $tag('/layout') ?>
	 * </code>
	 * 
	 * It works with the short syntaxes, as well: 
	 * <code>
	 * <? $result = $tag('layout/', [...]) ?>
	 * </code>
	 */ 
	protected function tag($name, $params = null) {
		$tagParamCount = \func_num_args();
		// TODO: Make this scoped (like say Java/C# imports) to the file calling $import(), allow up-scope imports if explicitly specified (i.e. "get the imports this import is including").
		$funcStack = & $this->tagFuncStack;
		$paramStack = & $this->tagParamStack;
		$funcs = & $this->tagFuncs;
		$result = null;
				
		// Register a new template function.
		if ($params instanceof \Closure) {
			if (isset($funcs[$name])) throw new \Exception('Template function named "' . $name . '" was already defined.');
			$funcs[$name] = $params;
			return;
		}
		
		// Self-closing tag <foo/>.
		if ($name[\strlen($name) - 1] === '/') {
			$selfClose = true;
			$name = \substr($name, 0, -1);
		} else {
			$selfClose = false;
		}
		
		// Closing tag </foo> vs. opening tag <foo>.
		if ($name[0] === '/') {
			$open = false; 
			$name = \substr($name, 1);
		} else {
			$open = true;
		}
		
		// Function parameter tag <@foo> vs. function tag <foo>.
		if ($name[0] === '@') {
			$param = true; 
			$name = \substr($name, 1);
		} else {
			$param = false;
		}
		
		
		// Shortcut <function@param> detection.
		if (\strpos($name, '@') !== false) {
			$name = \explode('@', $name);
			$shortcutParam = $name[1];
			$name = $name[0];
		} else {
			$shortcutParam = null;
		}
		
		if ($shortcutParam && !$open) {
			$this->tag('/@' . $shortcutParam);
		}
		
		if ($param) {
			if ($open) {
				if ($tagParamCount == 2) {
					if (!$selfClose) {
						throw new \Exception('When specifying a parameter value as a second parameter of $tag(), the parameter tag should be self-closing.');
					} else {
						$funcStack[\count($funcStack) - 1][1][$name] = $params;
					}
				} else {
					// Param open.
					$paramStack[] = $name;
					\ob_start();
				}
			} else { 
				// Param close.
				if ($name !== null && $name2 = \array_pop($paramStack) !== $name) {
					throw new \Exception('Parameter end mismatch: closing "' . $name . '", expecting to close "' . $name2 . '".');
				}
				
				$funcStack[\count($funcStack) - 1][1][$name] = \ob_get_clean();
			}
		} else {
			if ($open) { 
				// Function call open.
				if (!isset($funcs[$name])) throw new \Exception('Undefined template function "' . $name . '".');
				$funcStack[] = [$name, $params === null ? [] : $params];
			} else { 
				// Function call close.
				$func = \array_pop($funcStack);
		
				if ($name !== null && $func[0] !== $name) {
					throw new \Exception('Template function end mismatch: closing "' . $name . '", expecting to close "' . $func[0] . '".');
				}
				
				// TRICKY: The closures we get are effectively anonymous methods, as they're defined in the scope of 
				// a method, so we need to use ReflectionMethod, not ReflectionFunction, or upon invocation the closures
				// will be unaware of their $this context.
				$reflFunc = new \ReflectionFunction($funcs[$func[0]]);
				
				$params = [];
				foreach ($reflFunc->getParameters() as $reflParam) {
					$paramName = $reflParam->getName();
					
					if (\key_exists($paramName, $func[1])) {
						$params[] = $func[1][$paramName];
					} else {
						if ($reflParam->isOptional()) {
							$params[] = $reflParam->getDefaultValue();
						} else {
							throw new \Exception('Required parameter "' . $paramName . '" for template function "' . $func[0] . '" is missing.');
						}
					}
						
				}
				
				// Using ReflectionFunction::invokeArgs() would run the closure without object context (PHP issue).
				// So we're using older APIs to keep the context.
				$result = \call_user_func_array($funcs[$func[0]], $params);
			}
		}
		
		if ($shortcutParam && $open) {
			$this->tag('@' . $shortcutParam);
		}
		
		if ($selfClose && !$param) {
			$result = $this->tag('/' . $name);
		}
		
		return $result;
	}
	
	/**
	 * Escapes strings for HTML (and other targets). The assumed document charset is UTF-8.
	 * 
	 * For HTML, this method will gracefully return an empty string if you pass null (which happens when fetching a
	 * non-existing key from $data).
	 * 
	 * @param mixed $value
	 * A value to output (typically a string, but some formats, like "js" support also arrays and objects).
	 * 
	 * @param string $format
	 * Optional (default = 'html'). Escape formatting, supported values: 'html', 'js', 'none'. None returns the value
	 * unmodified, and is only included to make your code more readable when you apply escaping (or not) conditionaly.
	 */
	protected function esc($value, $format = 'html') {
		switch ($format) {
			case 'html':
				if ($value === null) return '';
				return \htmlspecialchars($value, \ENT_QUOTES, 'UTF-8');
				break;
			
			case 'js':
				return \json_encode($value, \JSON_UNESCAPED_UNICODE);
				break;
			
			case 'none':
				return $value;
				break;
				
			default:
				throw new \Exception('Unknown format "' . $format . '".');
		}
	}
	
	private function getShortcuts() {
		if ($this->shortcuts === null) {
			$class = new \ReflectionClass($this);
			
			$props = $class->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED); 
			
			foreach ($props as $prop) {
				$name = $prop->getName();
				$this->shortcuts[$name] = & $this->$name;
			}
			
			$methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED); 
			
			foreach ($methods as $method) {
				$name = $method->getName();
				if ($name === '__construct') continue; // That wouldn't make sense to have in the shortcuts.
				$this->shortcuts[$name] = $method->getClosure($this);
			}
		}
		
		return $this->shortcuts;
	}
}