<?php
namespace Solver\Shake;

/**
 * Non-fatal Controller exception, results in conditions handled by the base Controller class or the router invoking
 * the controller.
 * 
 * Do not throw directly, instead use the stop() method of your controller.
 * 
 * @author Stan Vass
 * @copyright © 2011-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class ControllerException extends Exception {}
?>