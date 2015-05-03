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
	 * A dict of inputs as passed by the router (for details, see \Solver\Sparta\Router::dispatch()), wrapped in a 
	 * DataBox instance for convenient data access.
	 *
	 * @var PageInput
	 */
	protected $input;

	/**
	 * A dict of parameters, which the page accumulates, and passes to the template(s).
	 *
	 * This together with the $log property form the "view model" of the page.
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
	 * @param PageModel $model
	 * Optional (default = null). A page model to pass to the template instead of $this->model. Setting this parameter
	 * should be rare, only when rendering auxiliary templates.
	 * 
	 * @param PageLog $log
	 * Optional (default = null). A page log to pass to the template instead of $this->log. Setting this parameter
	 * should be rare, only when rendering auxiliary templates.
	 *
	 * Special symbols:
	 * 
	 * - You can use "@" to refer to the page class name, so "@\ExampleTemplate", when executed for class "Foo\BarPage"
	 * will resolve to template id "Foo\BarPage\ExampleTemplate".
	 * - You can use '.' to refer to the page class namespace, so ".\ExampleTemplate", when when executed for class
	 * "Foo\BarPage" will resolve to template id "Foo\ExampleTemplate".
	 * 
	 * Without a dot "." or at "@", names are considered absolute.
	 * 
	 * The default value if you pass null (or nothing) for template id is "@\DefaultTemplate".
	 * 
	 * LEGACY: Page class names ending in Controller will be resolved by default (if templateId is null or not passed)
	 * to names ending in View. For example, for controller "Foo\BarController", the assumed template identifier will be
	 * "Foo\BarView". This will be removed in the future.
	 *
	 * For a detailed description of what a "template id" is, see AbstractTemplate::__construct().
	 */
	final protected function renderTemplate($templateId = null, PageModel $model = null, PageLog $log = null) {
		if ($model === null) $model = $this->model;
		if ($log === null) $log = $this->log;
		
		$class = get_class($this);
		$namespace = preg_replace('/^(.*)\\\\[\w\.\@]$/', '', $class);
			
		if ($templateId === null) {
			if (preg_match('/^(.*)Controller$/', $class, $matches)) { // Legacy resolution.
				$templateId = $matches[1] . 'View';
			} else { // New resolution.
				$templateId = '@\DefaultTemplate';
			}
		}
		
		$templateId = str_replace(['@', '.'], [$class, $namespace], $templateId);
		
		$template = new Template($templateId);
		$template($model, $log);
	}
	
	/**
	 * Identical to renderTemplate(), however instead of allowing the template to render to the output stream, it
	 * captures the output and returns it as a string.
	 * 
	 * Certain special actions, like the template setting HTTP headers can't be captured.
	 * 
	 * @param string $templateId
	 * Optional (default = null). Template id, see renderTemplate() for details.
	 * 
	 * @param PageModel $model
	 * Optional (default = null). A page model to pass to the template instead of $this->model. Setting this parameter
	 * should be rare, only when capturing auxiliary templates.
	 * 
	 * @param PageLog $log
	 * Optional (default = null). A page log to pass to the template instead of $this->log. Setting this parameter
	 * should be rare, only when capturing auxiliary templates.
	 */
	final protected function captureTemplate($templateId = null, PageModel $model = null, PageLog $log = null) {
		ob_start();
		$this->renderTemplate($templateId, $model, $log);
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
