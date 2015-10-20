<?php
namespace Solver\Accord;

use Solver\Logging\StatusLog;

/**
 * A generic abstraction for an executable unit of code.
 * 
 * We specify some restrictions, conventions and implied semantics, which make Actions more predictable and composable
 * than generic closures, and possible to expose in a standard way to outside parties (for ex. as public APIs via HTTP
 * etc.).
 * 
 * Every Action must abide by these rules (the rules refer to the apply() method semantics):
 * 
 * - An Action MUST always be atomic. It either completes in full, of fails with no output and observable side-effects.
 * TODO: Reconsider separate tag? We can't ask that pipelines, for example, exposed as actions that run other actions,
 * are atomic, without some global transaction support.
 * 
 * - An Action has a simplified failure mode by throwing an ActionException from apply(). An action doesn't categorize
 * its failure types in terms of "normal operation" or "exceptional failure". It just succeeds, or fails. An
 * ActionException always reports an empty message and code 0. Instead, any details about any errors that have occurred
 * while applying an action can be reported in the log provided by the caller (if any).
 * 
 * - An Action's apply() method SHOULD use only the standard failure mode of throwing ActionException + logging errors
 * to report a failure condition. Any alternative methods, like returning success flags or error codes from apply() are
 * highly discouraged, and should be a last resort, as they make the Action less predictable to intermediaries, and
 * hence less composable.
 * 
 * - When an Action succeeds, it MUST NOT log any "error" type events in the caller-provided log (if any). Error events
 * are strictly failure events, they never occur in a successful operation.
 * 
 * - When an Action fails, it MUST log one or more "error" type events in the caller-provided log (if any). If the 
 * action has no details for the error, it can log a single empty error event (no path, message, code or details). The
 * only exception to this rule is when the log has an event type mask which excludes errors (then logging an error
 * wouldn't have an effect anyway).
 * 
 * - When an Action succeeds, it MAY log "success" type events in the caller-provided log (if any). This is generally
 * not necessary for machine-readable APIs, but is useful for actions that sit closer to a human UI. In general, the 
 * absence of one or more error message in the log is a sufficient sign of action sucess on its own (unless the log
 * has explicitly excluded error events via its event type mask).
 * 
 * - When an Action fails, it MUST NOT log any "success" type events in the caller-provided log (if any).
 * 
 * - An Action MAY log "info" and "warning" type events no matter if it has failed or succeeded.
 * 
 * - An Action MUST NOT put events on the log that are unrelated to the execution of the current call. I.e. if you're
 * implementing, for example, a log reader action (for another log), do not return fetched events in the Action's log,
 * instead return them in your output. The Action log is strictly for meta information related to the currently executed
 * action.
 * 
 * - The order of events in the log should be from least general to most general for two reason. First, this is the 
 * most natural approach when an action invokes sub-actions with the same log: it can inspect the sub-action errors, if
 * any and append a general error after that. Second, when the caller inspects a log and encounters multiple errors, 
 * the easiest error to fetch is the last one, and make a decision upon it (if the type affects the decision). It can
 * then go further back in the log to retrieve details.
 * 
 * - Exception types other than ActionException thrown from apply() are permitted only for fatal conditions that the 
 * developer doesn't expect to occur during normal operation of the code. In such cases, the exception is considered an
 * unexpected internal application problem and is handled like any other internal failure, without any special
 * considerations than an Action threw an exception. Examples of this is throwing \Exception or AssertionError when
 * an action has failed due to mis-configuration (i.e. before apply() was called), or a colaborator object or service
 * that isn't expected to ever fail during normal operation has failed.
 * 
 * See also tag interfaces:
 * 
 * - NoEffect
 * - NoOutput
 * - IdempotentEffect
 * - IdempotentOutput
 */
interface Action {
	/**
	 * Executes the encapsulated action, or throws on failure.
	 * 
	 * @param mixed $input
	 * Arbitrary value for input, or null for no input. Null MUST be considered equivalent to passing no parameter.
	 * 
	 * @param StatusLog $log
	 * An optional log where any events that have occured during the action (errors, warnings, etc.) will be logged.
	 * 
	 * Note, if you don't want to check if the log is null every time you need to log an event in your apply()
	 * implementation, you can start with this line of code:
	 * 
	 * <code>
	 * $log = $log ?? NullLog::get();
	 * </code>
	 * 
	 * Now the remainder of the method can safely assume a log is always present. The balance between code performance
	 * and code clarity is up to the implementer. Another approach is using a real temp log inside apply() and then 
	 * merging it into the the caller-provided log before returning (if the caller provided any).
	 * 
	 * @return mixed
	 * Arbitrary value for output, or null for no output.
	 * 
	 * @throws ActionException
	 * If the action cannot be applied (bad input, internal failures and other).
	 */
	public function apply($input = null, StatusLog $log = null);
}