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

use Solver\Report\ErrorLog;

/**
 * Interface for "transforms", which take a value, and produce a transformed value.
 * 
 * You must respect the following contract:
 * 
 * - "MUST": Following this rule is mandatory.
 * - "SHOULD": Following this rule is highly recommended, but you're allowed to make necessary exceptions.
 * - "MAY": Denotes an action or a rule that is allowed and you may follow, but it's not a specific recommendation.
 * 
 * - A Transform has two outcomes. Success, in which the transformed value is returned without logging errors. Failure, 
 * in which null is returned and one or more errors are logged. A Transform MUST choose one of those two outcomes for
 * a specific $input when apply() is called and it MUST NOT mix those two modes.
 * 
 * - A Transform object MUST be clonable ($tfm = clone $tfm). Clones are shallow: passed-in object references won't be
 * cloned, but any internal state MUST be cloned. If your Transform creates a mutable stateful object internally, which
 * is part of its logical state, that MUST be cloned appropriately as well to preserve the intended clone semantics.
 * 
 * - A Transform SHOULD produce no semantically observable side-effects, for ex. changing global settings, mutating
 * objects accessible outside the transform, writing to files etc.
 * 
 * - A Transform MUST support nullary constructors (constructors without required parameters).
 * 
 * - A Transform's constructor MUST NOT be the only way to configure it. Instead you SHOULD prefer configuration
 * methods. The constructor MAY accept optional arguments as a shortcut for common configuration setup, as long as
 * an equivalent configuration can be achieved otherwise.
 * 
 * - A Transform MAY provide methods that configure the filters and validation tests performed on a transformed value.
 * 
 * - A Transform's configuration methods SHOULD be seggregated into filter-only and test-only methods, where applicable.
 * Avoid methods which mix filtering and validation logic at once.
 * 
 * - A Transform filter-only method SHOULD begin with a 2nd person imperative mood verb: "trim", "normalize", "map"; 
 * or begin with "to" ("toSomething" being short for "convertToSomething"): "toNumber", "toUpper". You SHOULD avoid
 * filter methods starting with "apply", in order to avoid confusion with the Transform::apply() method.
 * 
 * - A Transform test-only method name SHOULD preferably use the "is", "has" prefixes followed by a noun or adjective.
 * When these prefixes are unsuitable, the method name SHOULD prefer a 3rd person indicative mood verb. For negative
 * tests, replace "is" with "isNot", "has" with "hasNo", and prepend "not" in other cases. Examples: "isMin", "isOneOf",
 * "isNotOneOf", "hasLength", "hasNoPunctuation", "equals", "notEquals", "matchesRegex", "notMatchesRegex". 
 */
interface Transform {
	/**
	 * Returns a transformed version of the given value, or logs one or more errors and returns null, if the value can't
	 * be processed by this transform.
	 * 
	 * @param mixed $value
	 * Value to transform.
	 * 
	 * @param \Solver\Report\ErrorLog $log
	 * If extracting properly formatted data from the given value fails, errors will be logged here.
	 * 
	 * @param string $path
	 * Default null. An optional base path to log the errors at. 
	 * 
	 * @return null|mixed
	 * Transformed value, or null if the transform couldn't be perform on this input value.
	 */
	public function apply($value, ErrorLog $log, $path = null);
}