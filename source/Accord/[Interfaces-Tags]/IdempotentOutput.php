<?php
namespace Solver\Accord;

/**
 * This is a tag interface intended to be mark Action implementations that abide by the restrictions listed here.
 * 
 * Actions that implement IdempotentOutput, guarantee to callers the following semantics:
 * 
 * - Calling method apply() with a given $input parameter once, MUST produce the same output as when you call method
 * apply() with that output, i.e. if B == apply(A), then B == apply(B). In casual language this means you can run a
 * piece of input through such an Action once, or as many times as you want, and you can be sure you won't cause damage
 * to your data. For example, when you run a form field through am Action validator for the first time, whitespace may
 * be trimmed. If you feed it back the trimmed output, you get the same trimmed output.
 * 
 * - "Same output" is defined to mean semantically equivalent values given the action domain, for example if the
 * action documents that it doesn't discriminate string "5" from integer 5 in its output, then both are considered the
 * "same output". Rules of equality between two inputs may also consider a subset of fields in the input, instead of 
 * the entire input (this is contextual for every action).
 * 
 * - "Same output" also affects the produced log in a narrow sense. If a log capable of accepting errors is given to
 * apply() and B == apply(A), then apply(A) producing errors means apply(B) also MUST produce errors, and apply(A) not 
 * producing errors means apply(B) also MUST NOT produce errors. The content of the errors, their count, and any other
 * event types added to the log may vary, but errors require special treatment as they concern the end state of an
 * action. 
 * 
 * - This interface makes NO guarantees about the idempotency of an action's effects, see IdempotentEffect for this.
 */
interface IdempotentOutput {}