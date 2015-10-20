<?php
// Some ideas about future directions.
namespace Solver\Accord\_EXPERIMENTAL_;


// interface Transform extends Action, Purity, Determinism {	
// 	function apply($input = null, ErrorLog $log = null, $path = null) {}
// }

// interface Format extends Transform, Idempotency {
// 	function apply($input = null, ErrorLog $log = null, $path = null) {}
// }

// DeterministicEffect
	// RepeatableWrite; Same input produces always same effect. RepeatableEffect
// DeterministicOutput
	// RepeatableRead; Same input produces always same output. RepeatableOutput
// AtomicEffect <-- it's best this is always the case cause... who wants non-atomic actions.
	// Actions that apply in full only, never partially.
// Cloneable
// NullaryConstructable

/*
interface ToColumnFormat { <-- Batch process a column in a table. 
	functon toColumnFormat($sourceColumnName, $targetColumnName): Format; // Should columns be tuples for many source to many target cols? Or is this a special case.
}

Example usage, filtering resultsets which form a table (uniform 2D arrays, typically list of dicts, but not only).

Instead of doing:

$dict = (new DictFormat)->addRequired('isVerified', new BoolFormat());

$outRows = [];
foreach ($inRows as $i => $inRow) {
	$outRows[] = $dict->apply($inRow); // Implicit loop which calls another format, so we make (colCount * rowCount + rowCount) format apply() calls.
}

We can batch homogenous columns of data and avoid the loop:

$table = (new TableFormat)->addRequired('isVerified', new BoolFormat());

$outRows = $table->apply($inRows);

Internally, TableFormat tries to convert the given formats to columns when possible, for speed, if not, falls back to a
per-column loop for that column.

$colName = 'isVerified';
$colFormats[$colName] = $format instanceOf ToColumnFormat ? $format->toColumnFormat($colName, $colName) : $this->emulateColumnFormat($colName, $colName, $format);

Then (we pass everything every time, which is fine for local executions as it's by reference, which formats typically are at this level):

// It means columns formats should read a distinct set of columns and outputs a distinct set of cols. No random stuff or merge will be complex.
// Unless we pass output by reference... which for a format which is essentially an optimization feature MIGHT be acceptable. Gotta think about it.
$outRows = [];
foreach ($colFormats as $colFormat) {
	$outRows += $colFormat->apply($inRows); // Implicit loop which DOES NOT call another format (unless emulate), so we make (rowCount) format apply() calls.
}

This still requires we have the input in columnar format. We can avoid this by making column formats output like this:

input [$input, $partialOutput] => [$newPartialOutput];

The problem is this will temporarily create a copy of partial output before it's reassigned to $newPartialOutput at the caller. But this keeps a format being a Format.

Alternatively:


input [$input, & $output] => no output;

Modifying in place solves the problem but now this is not a valid Format. Technically it's idempotent, but it modifies what is its input.

Maybe a bench will decide. Test all three approaches:

1. Convert outside to columns, pass in all columns, output new columns, += to output.
2. Keep as-is (not columns), pass input, partial output, receive patched output (copies implicitly).
3. Keep as-is (not columns), pass input, reference to output, modify output in-place (no copying, but is not a Format anymore, technically).



Should properties be at the action level? Gets heavy no. Flags which an action call might *contain*:

atomic
idemeffect
idemoutput
nooutput
noeffect
deterministic
isolated
leastonce <-- deliver at least once message transport semantics
mostonce <-- same as idemeffect (intermediary can do this then)

And more... (some overlap with top stuff, so think about it)
command <-- request effect
query <-- requests output (note we can have command + query, which is a command with effect and output)
event <-- notifies of event occured elsewhere (how is this distinct than command?)

Might be call-specific so we should put this in the CALL. This way one call to same action may be idempotent another not.

A more practical way is to have:

interface DynamicAction extends Action {
	function apply($input = null, $log = null, $flags = 0) {
	}
	function fastApply(...) {
	}
}

And then have even more special logic (or wrappers) for it. DynamicAction only when needed maybe. Yup.

It won't actually add extra call logic I think, it's compatible with plain calls due to default, they just need to 
always pass mask of flags to all actions:

function (Format|DynamicAction $format) {
	$formatFlags = NO_EFFECT | DETERMINISTIC | ISOLATED | IDEMPOTENT_OUTPUT;
	$format->apply($input, $log, $formatFlags); <-- Format ignores and DynamicAction abides. what about failure mode though... hmm... is std ActionException good? I guess.
}

There is no type problem because if you don't support dynamic actions then you don't ... support them (need to be in the typehint). And when explicitly added, the method signals it supports them.

Only problem is fastApply()... Think about this.

Well. It'll have to be:

fastApply($input, & $output, $emask, & $events, $path, $amask) {}

Thought: can we roll DynamicAction's flags into *input*? It can be $dynamicInput = [$mask, $input], to preserve 100% compat with old Actions. But that breaks compat a little. Liskov would be sad.

Another approach: dynamicApply(). Then maybe also dynamicFastApply(). This would mean more special handling in caller, but maybe it can be abstracted to have a good common fast path and emulation for the rest. 

Think about it.

*/