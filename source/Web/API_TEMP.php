<?php
use Solver\Accord\FromValue;
use Solver\Accord\ToValue;
use Solver\AccordX\ExpressLog;
use Solver\Logging\StatusLog;
use Solver\Accord\Action;

class _Page implements Action {
	function apply(array $input, StatusLog $log) {}	
}

class _PageView {
	function apply($model);	
	function apply2($response);
}


class Renderable {
	render($reponse);
}

implement(PageInput $input, PageLog $log) {
	throwit
}

$this->view(Templateid)->render()
					   ->capture()
					   
					   
					   
					   $model->at(path)->get
										->set(
										
						$model(path)
						
Http

Handler implements Action {
	handle(RequestReader $request, ResponseWriter $response, ResponseLog $log) {
	}
}
			
class Model {
	__invoke('key') <- get
	__invoke($key, $val) <- set
	__invoke($key, null) <- delete
	__invoke('events:' . $key ) <- get events, errors,
	('foo.bar')->getEvents()
	('foo.bar')->get()
	('foo.bar')->get()
}

$model->events(

$model->withPath('foo.bar')->

$model->set('foo.bar', $val);
$model->set('foo.bar', $val);

Uniaccess;
class RequestReader {
	query($path = null);
	body($path = null);
	url();
	path();
	secure();
	protocol();
	matrix($path = null);
	headers($path = null, $all = false);
	router($path = null);
	server($path = null);
	cookie($path);
}

public function __get($name) {
	$readonly = $this->__readonly;
	
	//
	if (isset($readonly[$name]) || key_exists($name, $readonly)) {
		return $readonly[$name];
	}
}

class ResponseWriter {
	status($code);
	header($name, $value, $append = false);
	cookie($name, Cookie $cookie);
	write($string);
}

class ResponseModelWriter {
	events($path = null, $value = );
	errors($path = null);
	__invoke($path = null
}

class Handler {
	handle(RequestReader $request, ResponseWriter $response, HandlerLog $log) {
	}
}
