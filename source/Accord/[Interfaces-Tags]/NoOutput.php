<?php
namespace Solver\Accord;

/**
 * This is a tag interface intended to be mark Action implementations that abide by the restrictions listed here.
 * 
 * Actions that implement NoOutput, guarantee to callers the following semantics:
 * 
 * - Calling method apply() MUST always produce no output (i.e. it always returns null). In a casual language, such
 * actions are "write-only" and a caller may invoke them asynchronously as this tag interface is a guarantee that the
 * action won't produce output, which the caller has to read back. This interface is useful for modeling actions which
 * require no output, such as sending a command, or broadcasting an event.
 * 
 * - Calling method attempt() MUST always return true (success) and assign null to the output reference.
 * 
 * - "No output" above also includes the log, if a log was passed to the apply() method. An action implementing this
 * interface MUST NOT add events (including errors) of any kind to a log given to it.
 */
interface NoOutput extends IdempotentOutput {}