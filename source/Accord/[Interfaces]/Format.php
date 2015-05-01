<?php
/*
 * Copyright (C) 2011-2015 Solver Ltd. All rights reserved.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at:
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations under the License.
 */
namespace Solver\Accord;

use Solver\Logging\ErrorLog;

/**
 * Interface for "formats", which take a value, and return a normalized, validated (for the format) value.
 * 
 * You must respect the following contract:
 * 
 * - "MUST": Following this rule is mandatory.
 * - "SHOULD": Following this rule is highly recommended, but you're allowed to make necessary exceptions.
 * - "MAY": Denotes an action or a rule that is allowed and you may follow, but it's not a specific recommendation.
 * 
 * - All Format implementations MUST respect the specified contract rules for the Transform interface as well.
 * 
 * - A Format MUST be deterministic, i.e. it must always produce the same output if given the same inputs. You can't
 * have a filtering step which is pseudo-random, for example. If you need non-deterministic behavior, implement
 * Transform instead of Format.
 * 
 * - A Format MUST be idempotent, i.e. if method apply() is fed input A, and it returns output B, then if fed back B it
 * MUST produce B again. In casual language this means that unlike a Transform, a Format implementation allows you to
 * run a piece of input through a Format as many times as you want, and you can be sure you won't cause damage to your
 * data. For example, when you run a form field through a Format for the first time, whitespace may be trimmed. If you 
 * feed it back the trimmed output, you get the same trimmed output. If you need non-idempotent behavior, implement
 * Transform instead of Format.
 * 
 * - A Format SHOULD be pure, i.e. it should not rely on any global settings, configuration, state, I/O to affect its
 * output or behavior. The goal is to create Formats that behave predictably and are configured explicity through their
 * constructor and methods. If you need explicitly impure behavior, implement Transform instead of Format.
 * 
 * TODO: Some things cut for now due to lack of time:
 * - Unified error override interface (useError).
 * - Core format interfaces.
 * - Core format factories.
 */
interface Format extends Transform {
	/**
	 * Returns the canonical (for this format) version of the given value, or logs one or more errors and returns null,
	 * if the value can't be interpreted to match this format.
	 * 
	 * @param mixed $value
	 * Value to format.
	 * 
	 * @param \Solver\Logging\ErrorLog $log
	 * If the transform of the given value fails, errors will be logged here.
	 * 
	 * @param string $path
	 * Default null. An optional base path to log the errors at. 
	 * 
	 * @return null|mixed
	 * The canonical & valid representation of the value, or null if no such representation could be extracted.
	 */
	public function apply($value, ErrorLog $log, $path = null);
}