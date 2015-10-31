<?php
namespace Solver\Accord;

// TODO: AggregateAction (or BatchAction).

// Reads input according to the structure and applies the given actions. Returns aggregate results.
// Is this in part duplicating DictFormat & TupleFormat? Poorly? Think about it. If not, maybe worth having.

// Standin class for the example. Not really pipeline.
$action = new PipelineAction(); 

// Standin class for the example. Not really pipeline.
$batch = (new PipelineAction([
	'foo' => $action,
	'bar' => [
		'one' => $action,
		'two' => $action,
		'three' => [$action, $action, $action],
	],
	'baz' => $action,
]))->apply($input = null, $log = null);