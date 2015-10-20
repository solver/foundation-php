<?php
namespace Solver\Accord;

/**
 * Provides an optional alternative calling convention to apply() (completely isomorphic to it) with the pure goal of 
 * better performance for small actions which participate in large action compositions, and the overhead of the standard
 * call semantics may be slowing the code down.
 * 
 * This interface uses a boolean return flag instead of exceptions to communicate failure, and it uses an "unrolled" log
 * format consisting of an explicitly passed event type mask, event array, and path prefix.
 * 
 * Code which calls an action can quickly check "$action instanceof FastAction" and use the fast calling convention
 * when available.
 * 
 * IMPORTANT: This calling convention is faster, but is much harder to get right. Only implement this interface for 
 * highly reusable, stable library actions, and not for application-level code.
 * 
 * Actions which implement this interface, can use trait ApplyViaFastApply to automatically implement apply() for 
 * compatibility with the parent Action interface. See also ActionUtils::emulateFastApply().
 */
interface FastAction extends Action {	
	/**
	 * Executes the encapsulated action and returns true, or returns false on failure. The output is assigned to the 
	 * given output parameter by reference.
	 * 
	 * The executed action is identical to apply(), and with the same semantics, but a different calling convention.
	 * 
	 * Using this method should be avoided for high-level application code, but is more performant (no thrown exceptions
	 * on failure) for big compositions of small actions, where failure is not exceptional, but part of normal operation
	 * flow.
	 * 
	 * You don't have to manually implement both apply() and fastApply() in your Action classes. See:
	 * 
	 * - trait ApplyViaFastApply, implements apply() for you, if you have implemented fastApply().
	 * - static method ActionUtils::emulateFastApply(), emulates fastApply() for Action that don't implement it.
	 * 
	 * @param mixed $input
	 * Arbitrary value for input. Null MUST be considered semantically equivalent to passing no input.
	 * 
	 * @param mixed & $output
	 * Reference to which output will be assigned. When the action fails (method return value = false), the value
	 * assigned to $output MUST be null. Output is not permitted for a failed action.
	 * 
	 * IMPORTANT: To ensure proper semantics, method fastApply() MUST always initialize $output by replacing its entire
	 * value, for ex. $output = null, or $output = [], etc. before it reads from it, and before it attempts to modify
	 * it partially via array access or otherwise. 
	 * 
	 * Any reads or partial modifications of $output before it's overwritten represent a side-effect which is strictly
	 * against the semantics of this parameter (which stands in for action result).
	 * 
	 * @param $mask
	 * Default 0 (no events will be logged). The caller needs to explicitly declare they want any events by passing an 
	 * appropriate mask here (typically 15, or 0b1111, for the four major StatusLog event types).
	 * 
	 * Note that just like acknowledging the mask is optional when writing to a StatusLog, acknowledging the $mask by
	 * fastApply() implementation is optional as well. If the caller passes $events reference, they might still get
	 * StatusLog compatible events that are excluded by the mask. The need for actions to check the mask before adding 
	 * events should be considered an optional optimization that an action might skip. For example, actions may choose 
	 * to check the mask only for non-error messages (as it's very rare not to log errors).
	 * 
	 * @param array & $events
	 * list<dict> Reference to a list where any events that have occured during the action (errors, warnings, etc.) can
	 * be appended.
	 * 
	 * IMPORTANT: To ensure proper semantics, fastApply() MUST NOT read the events given in this variable in any way
	 * (checking count, reading actual events), or attempt to mutate it in any way different than appending events to
	 * it via push $events[] = $event, array_merge, array_push and similar means to append to the list. Method
	 * fastApply() MUST NOT replace the entire $events array with a new array, as the given reference often will already
	 * contain events which should stay there.
	 *  
	 * Any reads or mutations other than the listed here represent a side-effect, which is strictly against the
	 * semantics of this parameter (which stands in for the append-only action log). One exception is checking if
	 * $events is empty in order to replace it rather than push to it (pure optimization).
	 * 
	 * @param string $path
	 * An optional base input path that the log will use when creating events.
	 * 
	 * @return bool
	 * True for action success, false for action failure. "Failure" here stands for the identical conditions under 
	 * which apply() would throw ActionException.
	 */
	function fastApply($input = null, & $output = null, $mask = 0, & $events = null, $path = null);
}