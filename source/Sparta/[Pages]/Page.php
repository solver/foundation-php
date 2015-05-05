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

/**
 * Base class for page handlers (i.e. controllers, in a typical web MVC framework).
 * 
 * You don't need to use this particular class, the dispatcher and router will accept any callable format. This class
 * is one possible base class for a page controller, and provides shortcuts and functionality typically needed for it.
 */
abstract class Page {
	/**
	 * Strips page class suffix when resolving relative $templateId, see render() for details.
	 * 
	 *  Set this property to change the default behavior (null = no suffix stripping).
	 * 
	 * @var null|string
	 */
	protected $templateIgnoreSuffix = 'Page';
	
	/**
	 * Sets the base "theme" namespace when resolving relative $templateId, see render() for details.
	 * 
	 * Set this property to change the default behavior (null = use the namespace of the current Page class instance).
	 * 
	 * @var null|string
	 */
	protected $templateBaseNamespace = null;
	
	/**
	 * Sets the default template id used when you pass null (or nothing) to the render/capture methods.
	 * 
	 * Set this property to change the default behavior (null = disable default template id).
	 * 
	 * @var null|string
	 */
	protected $templateDefaultId = '#\MainLayout';
	
	/**
	 * A dict of inputs as passed by the router (for details, see \Solver\Sparta\Router::dispatch()), wrapped in an 
	 * object providing convenient & safe data access.
	 *
	 * @var PageInput
	 */
	protected $input;

	/**
	 * A dict of parameters, which the page accumulates, and passes to the template(s).
	 *
	 * This together with the $log property form the "view model" of the page. Do not confuse this with a domain model,
	 * whose role is fulfilled by services. This model is defined *by* the page controller *for* the view template.
	 *
	 * @var PageModel
	 */
	protected $model;

	/**
	 * A log of success/info/warning/error events, which the page accumulates, and passes to the template(s).
	 *
	 * This is part of the "viewmodel" in the framework. The other part is property $model.
	 *
	 * @var PageLog
	 */
	protected $log = [];

	final public function __invoke(array $input) {
		try {
			$this->input = new PageInput($input);
			$this->model = new PageModel();
			$this->log = new PageLog();
			$this->main();
		} catch (\Exception $e) {
			if ($e instanceof PageException) {
				// TODO: Eating the exception when the code is 0 is a feature we should reconsider (needed for the time
				// being as a flow control clutch when a super method wants to display an error and make sure the sub
				// method doesn't execute /say, after redirecting to a login page/). 
				if ($e->getCode() != 0) throw $e;
			} else {
				// Any other exception type is a sign of unexpected application failure.
				
				// For development setups, let it bubble up.
				if (\DEBUG) throw $e;

				// For production setups, log it and continue to a status 500 page.
				\error_log(sprintf("%s: %s\n%s",
					get_class($e),
					$e->getMessage(),
					$e->getTraceAsString()
				));
				
				throw new PageException(null, 500, $e);
			}
		}
	}

	/**
	 * This method will get called when the dispatcher invokes a page. Define this method in your subclass.
	 */
	abstract public function main();

	/**
	 * Renders a view template to standard output.
	 *
	 * @param string $templateId
	 * Optional (default = null).
	 * 
	 * Conventions:
	 * 
	 * - It's recommended to name your pages with suffix "Page", like IndexPage, ContactPage, NewsPage etc.
	 * - There are three types of templates, and you should name them according to their intended usage.
	 * - 1. Layout Template, produces a complete page response when called - use suffix "Layout".
	 * - 2. Partial Template, produces a fragment of a page responce when called - use suffix "Partial".
	 * - 3. Tags Template, passively defines one or more tags for reuse in other templates - use suffix "Tag" or "Tags".
	 * - It's recommended to put templates in a namespace named after the page they're made for (if any), without the
	 * "Page" suffix, i.e. templates for IndexPage should be at Index\MainLayout, Index\DetailsLayout etc.
	 *
	 * Specially interpreted symbols in $templateId:
	 * 
	 * - Start with "." to specify a templateId relative to the base template namespace.
	 * - Start with "#' to specify a templateId relative to the template namespace for the current page class.
	 * - All other names are considered absolute (don't prefix a leading slash for absolute names).
	 * - See properties $templateIgnoreSuffix, $templateNamespace to customize how the above symbols resolve.
	 * 
	 * Example resolutions (where "suffix" refers to $templateIgnoreSuffix, and "ns" refers $templateBaseNamespace).
	 *  
	 * With $templateIgnoreSuffix = "Page", $templateBaseNamespace = null (defaults):
	 * - Page = "Vendor\Foo\BarPage", template = ".\QuxLayout", resolution: "Vendor\Foo\QuxLayout".
	 * - Page = "Vendor\Foo\BarPage", template = "#\QuxLayout", resolution: "Vendor\Foo\Bar\QuxLayout".
	 * 
	 * With $templateIgnoreSuffix = null, $templateBaseNamespace = null:
	 * - Page = "Vendor\Foo\BarPage", template = ".\QuxLayout", resolution: "Vendor\Foo\QuxLayout".
	 * - Page = "Vendor\Foo\BarPage", template = "#\QuxLayout", resolution: "Vendor\Foo\BarPage\QuxLayout".
	 * 
	 * With $templateIgnoreSuffix = "Controller", $templateBaseNamespace = "Foo\Views":
	 * - Page = "Foo\Controllers\BarController", template = ".\QuxView", resolution: "Foo\Views\QuxView".
	 * - Page = "Foo\Controllers\BarController", template = "#\QuxView", resolution: "Foo\Views\Bar\QuxView".
	 * 
	 * If you pass null (or nothing) for template id, it's set to "#\MainLayout" (override via $templateDefaultId).
	 *
	 * For a detailed description of what a "template id" is, see AbstractTemplate::__construct().
	 */
	final protected function render($templateId = null) {
		$this->renderWith($this->model, $this->log, $templateId);
	}
	
	/**
	 * Identical to render(), however instead of allowing the template to render to the output stream, it
	 * captures the output and returns it as a string.
	 * 
	 * Certain special actions, like the template setting HTTP headers can't be captured.
	 * 
	 * @param string $templateId
	 * Optional (default = null). Template id, see render() for details.
	 */
	final protected function capture($templateId = null) {
		return $this->captureWith($this->model, $this->log, $templateId);
	}
	
	/**
	 * Identical to render(), but using the supplied page model and log, instead of $this->model and $this->log.
	 * 
	 * Using this method instead of render() should be rare, primarily when rendering auxiliary templates.
	 * 
	 * @param PageModel $model
	 * Optional (default = null). A page model to pass to the template instead of $this->model. 
	 * 
	 * @param PageLog $log
	 * Optional (default = null). A page log to pass to the template instead of $this->log.
	 * 
	 * @param string $templateId
	 * Optional (default = null). See render() for details.
	 */
	final protected function renderWith(PageModel $model, PageLog $log, $templateId = null) {
		if ($templateId === null) {
			if ($this->templateDefaultId === null) {
				throw new \Exception('Default template id resolution has been disabled. Pass an explicit template id to render/capture.');
			} else {
				$templateId = $this->templateDefaultId;
			}	
		}
		
		$first = $templateId[0];
		
		if ($first === '.') {
			$baseNamespace = $this->templateBaseNamespace;
			if ($baseNamespace === null) $baseNamespace = preg_replace('@\\\\?\w+$@', '', get_class($this));
			$templateId = $baseNamespace . substr($templateId, 1);
		}
		
		elseif ($first === '#') {
			$class = get_class($this);
			$baseNamespace = $this->templateBaseNamespace;
			if ($baseNamespace === null) $baseNamespace = preg_replace('@\\\\?\w+$@', '', $class);
			
			$ignoreSuffix = $this->templateIgnoreSuffix;
			if ($ignoreSuffix !== null) $class = preg_replace('@' . preg_quote($ignoreSuffix) . '$@', '', $class);
			
			$baseClass = preg_replace('@(.*\\\\)(?=\w+$)@', '', $class);
			$templateId = $baseNamespace . '\\' . $baseClass . substr($templateId, 1);
		}
		
		$template = new Template($templateId);
		$template($model, $log);
	}
	
	/**
	 * Identical to capture(), but using the supplied page model and log, instead of $this->model and $this->log.
	 * 
	 * Using this method instead of capture() should be rare, primarily when capturing auxiliary templates.
	 * 
	 * @param PageModel $model
	 * Optional (default = null). A page model to pass to the template instead of $this->model. 
	 * 
	 * @param PageLog $log
	 * Optional (default = null). A page log to pass to the template instead of $this->log.
	 * 
	 * @param string $templateId
	 * Optional (default = null). See render() for details.
	 */
	final protected function captureWith(PageModel $model, PageLog $log, $templateId = null) {
		ob_start();
		$this->renderWith($model, $log, $templateId);
		return ob_get_clean();
	}
	
	/**
	 * Stops controller execution.
	 *
	 * Think about this as an "exit()" directive that only applies to code contained in & invoked by Controller classes.
	 * 
	 * Example: the user is not logged it, render the "log in" view template & stop further controller code execution.
	 * 
	 * <code>
	 * if ($notLoggedIn) {
	 * 		$this->renderView('Foobar\LogIn');
	 * 		$this->stop();
	 * }
	 * </code>
	 */
	final protected function stop() {
		throw new PageException(null, 0);
	}

	/**
	 * Stops controller execution & requests the router to redirect to the controller for given HTTP status.
	 *
	 * This is a variation of stop(), with a different behavior after the controller stops execution.
	 *
	 * Example: the user is not logged in, stop execution & re-route to the default "403 Forbidden" handler (no need to
	 * render a view, as the 403 handler is a controller itself that can have its own view).
	 *
	 * <code>
	 * if ($wrongUrl) {
	 * 		$this->stopWithStatus(404);
	 * }
	 * </code>
	 *
	 * @param number $httpStatus
	 * Optional (default = 0). Pass an HTTP status for the router to re-route to its error handler.
	 */
	final protected function stopWithStatus($httpStatus) {
		if ($httpStatus == 0) throw new \Exception('Invalid HTTP status.');
		throw new PageException(null, $httpStatus);
	}
		
	/**
	 * Stops controller execution & sends an HTTP redirect header to the browser/client.
	 * 
	 * This is a variation of stop(), with a different behavior after the controller stops execution.
	 * 
	 * Example: the user is not logged it, render the "log in" view template & stop further controller code execution.
	 * 
	 * <code>
	 * if ($notLoggedIn) {
	 * 		$this->stopAndRedirect('/log-in/');
	 * }
	 * </code>
	 *
	 * @param string $url
	 * URL to redirect to.
	 *
	 * @param int $httpStatus
	 * Optional (default 307). Status code for the redirect.
	 */
	final protected function stopAndRedirect($url, $httpStatus = 307) {
		switch ($httpStatus) {
			case 301:
				$httpStatus = '301 Moved Permanently';
				break;
				
			case 302:
				$httpStatus = '302 Found';
				break;
				
			case 303: // HTTP 1.1 only
				$httpStatus = '303 See Other';
				break;
				
			case 307: // HTTP 1.1 only
				$httpStatus = '307 Temporary Redirect';
				break;
				
			default:
				throw new \Exception('Bad redirect status code: ' . $httpStatus);
		}

		\header($this->input->get('server.SERVER_PROTOCOL') . ' ' . $httpStatus);
		\header('Location: ' . $url);
		$this->stop();
	}
}
