<?php
namespace Solver\Accord;

/**
 * This is a tag interface intended to be mark Action implementations that abide by the restrictions listed here.
 * 
 * Actions that implement IdempotentEffect, guarantee to callers the following semantics:
 * 
 * - Calling method apply() with a given $input parameter once, MUST produce the same observable effects as when
 * you call method apply() with the same input two or more times. In a casual language, actions that implement
 * IdempotentEffect are "write-once", so you can safely call the action multiple times, if for whatever reason you
 * aren't sure if the first time the action was called (transport layer problems) or it significantly simplifies your
 * application logic not to keep track if an effectful action was called once or more times.
 * 
 * - "Observable effects" include internal action state changes that can may modify its behavior, altering shared
 * mutable state, or interacting with I/O devices in a stateful way (for ex. sending messages, etc.). There are 
 * exceptions to this rule when it comes to developer debug logs, basic action access logs, output cache generation 
 * (memoization), and other activities which don't affect the observable domain from the PoV of the caller.
 * 
 * - "Same input/output" is defined to mean semantically equivalent values given the action domain, for example if the
 * action doesn't differentiate string "5" from integer 5 in input, then both are considered the "same input". Rules of 
 * equality between two inputs may also consider a subset of fields in the input, instead of the entire input (this is 
 * contextual for every action).
 * 
 * - This interface makes NO guarantees about the idempotency of an action's output, see IdempotentOutput for this.
 */
interface IdempotentEffect {}