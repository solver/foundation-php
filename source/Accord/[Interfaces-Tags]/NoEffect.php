<?php
namespace Solver\Accord;

/**
 * This is a tag interface intended to be mark Action implementations that abide by the restrictions listed here.
 * 
 * Actions that implement NoEffect, guarantee to callers the following semantics:
 * 
 * - Calling method apply() MUST always produce no observable effects. In a casual language, actions that implement
 * NoEffect are "read-only", so you can safely call the action through automated agents, for example an action that
 * implements NoEffect can safely be exposed over HTTP GET, where it may be invoked by search engine crawlers, browser
 * prefetchers etc. Actions that implement NoEffect are useful for modeling pure data queries (the opposite of NoOutput
 * which is useful for modeling pure commands).
 * 
 * - "Observable effects" include internal action state changes that can may modify its behavior, altering shared
 * mutable state, or interacting with I/O devices in a stateful way (for ex. sending messages, etc.). There are 
 * exceptions to this rule when it comes to developer debug logs, basic action access logs, output cache generation 
 * (memoization), and other activities which don't affect the observable domain from the PoV of the caller.
 */
interface NoEffect extends IdempotentEffect {}