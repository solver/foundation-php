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
namespace Solver\Lab;

/**
 * Base class for page handlers (i.e. controllers, in a typical web MVC framework).
 * 
 * You don't need to use this particular class, the dispatcher and router will accept any callable format. This class
 * is one possible base class for a page controller, and provides shortcuts and functionality typically needed for it.
 */
abstract class Page {
	/**
	 * A dict of inputs as passed by the router (for details, see \Solver\Lab\Router::dispatch()), wrapped in a 
	 * DataBox instance for convenient data access.
	 *
	 * @var \Solver\Lab\DataBox
	 */
	protected $input;

	/**
	 * A dict of parameters, which the page accumulates, and passes to the template(s).
	 *
	 * This is part of the "viewmodel" in the framework. The other part is property $log.
	 *
	 * @var array
	 */
	protected $data = [];

	/**
	 * A log of success/info/warning/error events, which the page accumulates, and passes to the template(s).
	 *
	 * This is part of the "viewmodel" in the framework. The other part is property $data.
	 *
	 * @var \Solver\Lab\PageLog
	 */
	protected $log = [];

	final public function __invoke(array $input) {
		try {
			$this->input = new DataBox($input);
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
	 * Renders a view template.
	 *
	 * @param string $templateId
	 * Optional (default = null).
	 *
	 * By default this method will automatically resolve the template name from the page class name. For example, for
	 * controller "Foo\BarPage", the assumed template identifier will be "Foo\BarTemplate".
	 * 
	 * For legacy reasons names ending with Controller will be resolved to names ending in View as well. For example,
	 * for controller "Foo\BarController", the assumed template identifier will be "Foo\BarView".
	 *
	 * Or you can pass any string to render a custom template id
	 *
	 * For a detailed description of what a "template id" is, see AbstractView::render().
	 */
	final protected function renderTemplate($templateId = null) {
		if ($templateId === null) {
			$class = get_class($this);
			$templateId = $class;
			$templateId = preg_replace('/Page$/', 'Template', $templateId);
			$templateId = preg_replace('/Controller$/', 'View', $templateId);
			
			if ($class === $templateId) {
				throw new \Exception('The page class does not use a standard suffix, and a template name cannot be resolved automatically.');
			}
		}
		
		$template = new Template($templateId);
		$template($this->data, $this->log);
	}
	
	/**
	 * Legacy alias. See renderTemplate().
	 * 
	 * @param string $templateId
	 */
	final protected function renderView($templateId = null) {
		$this->renderTemplate($templateId);
	}
	
	/**
	 * Identical to renderTemplate(), however instead of allowing the template to render to the output stream, it
	 * captures the output and returns it as a string.
	 * 
	 * Certain special actions, like the template setting HTTP headers can't be captured.
	 * 
	 * @param string $templateId
	 * Optional (default = null). Template id, see renderTemplate() for details.
	 */
	final protected function captureTemplate($templateId = null) {
		ob_start();
		$this->renderTemplate($templateId);
		return ob_get_clean();
	}
	
	/**
	 * Legacy alias. See captureTemplate().
	 * 
	 * @param string $templateId
	 */
	final protected function captureView($templateId = null) {
		$this->captureTemplate($templateId);
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
