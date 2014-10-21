<?php
/*
 * Copyright (C) 2011-2014 Solver Ltd. All rights reserved.
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
 * Base class for controllers.
 */
abstract class Controller {
	/**
	 * A dict of inputs as passed by the router (for details, see \Solver\Lab\Router::dispatch()), wrapped in a 
	 * DataBox instance for convenient data access.
	 *
	 * @var \Solver\Lab\DataBox
	 */
	protected $input;

	/**
	 * A dict of parameters, which the controller accumulates, and passes to the view(s).
	 *
	 * This is part of the "viewmodel" in the framework. The other part is property $log.
	 *
	 * @var array
	 */
	protected $data = [];

	/**
	 * A log of success/info/warning/error events, which the controller accumulates, and passes to the view(s).
	 *
	 * This is part of the "viewmodel" in the framework. The other part is property $data.
	 *
	 * @var \Solver\Lab\ControllerLog
	 */
	protected $log = [];

	final public function __construct(array $input) {
		try {
			$this->input = new DataBox($input);
			$this->log = new ControllerLog();
			
			// REVISE: Calling main() automatically prevents a controller from being instantiated and configured in one
			// place, and passed to another place to be called. Consider removing this call from here.
			$this->main(); 
		} catch (\Exception $e) {
			if ($e instanceof ControllerException) {
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
				throw new ControllerException(null, 500, $e);
			}
		}
	}

	/**
	 * This method is automatically called when the router invokes a controller. Define this method in your subclass.
	 */
	abstract public function main();

	/**
	 * Renders a view template.
	 *
	 * @param string $templateId
	 * Optional (default = null).
	 *
	 * By default this method will automatically resolve the template name from the controller's name. For example, for
	 * controller "Foo\BarController", the assumed template identifier will be "Foo\BarView".
	 *
	 * Or you can pass any string to render a custom template id
	 *
	 * For a detailed description of what a "template id" is, see AbstractView::render().
	 */
	final protected function renderView($templateId = null) {
		$view = new View(
			$this,
			$this->data,
			$this->log
		);
		
		$view->render($templateId === null ? \substr(\get_class($this), 0, -10) . 'View' : $templateId);
	}
	
	/**
	 * Identical to renderView(), however instead of allowing the view to render to the output stream, it captures the
	 * output and returns it as a string.
	 * 
	 * Certain special actions, like the view setting HTTP headers can't be captured.
	 * 
	 * TODO: Better name for this function (renderViewToString?) or roll it into renderView as a parameter?
	 * 
	 * @param string $templateId
	 * Optional (default = null). Template id, see renderView() for details.
	 */
	final protected function captureView($templateId = null) {
		\ob_start();
		$this->renderView($templateId);
		return \ob_get_clean();
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
		throw new ControllerException(null, 0);
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
		throw new ControllerException(null, $httpStatus);
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
