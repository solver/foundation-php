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

/**
 * Interface for "transform" actions, which take a value, and produce a transformed value deterministically.
 * 
 * You must respect the following contract:
 * 
 * - A Transform abides by the rules listed for interface NoEffect: no side-effects. See NoEffect for more info.
 * 
 * - A Transform MUST be deterministic, i.e. it must always produce the same output if given the same inputs. You can't
 * have a filtering step which is pseudo-random, for example. If you need non-deterministic behavior, implement
 * Action instead of Transform.
 * 
 * - A Transform SHOULD be pure, i.e. it should not explicitly access global settings, configuration, state, I/O to
 * affect its output or behavior. The goal is to create Transforms that behave predictably and are configured explicity
 * through their constructor and methods. If you need explicitly impure behavior, implement Action instead of Transform.
 * 
 * - A Transform object MUST be clonable ($tfm = clone $tfm). Clones are shallow: passed-in object references won't be
 * cloned, but any internal state MUST be cloned. If your Transform creates a mutable stateful object internally, which
 * is part of its logical state, that MUST be cloned appropriately as well to preserve the intended clone semantics.
 * 
 * - A Transform MUST support nullary constructors (constructors without required parameters). TODO: Reconsider?
 * 
 * - A Transform's constructor MUST NOT be the only way to configure it. Instead you SHOULD prefer configuration
 * methods. The constructor MAY accept optional arguments as a shortcut for common configuration setup, as long as
 * an equivalent configuration can be achieved otherwise. TODO: Reconsider?
 * 
 * - A Transform MAY provide methods that configure the filters and validation tests performed on a transformed value.
 * 
 * - A Transform's configuration methods SHOULD be seggregated into filter-only and test-only methods, where applicable.
 * Avoid methods which mix filtering and validation logic at once.
 * 
 * - A Transform filter-only method SHOULD begin with a 2nd person imperative mood verb: "trim", "normalize", "map"; 
 * or begin with "to" ("toSomething" being short for "convertToSomething"): "toNumber", "toUpper". You SHOULD avoid
 * filter methods starting with "apply" & "attempt", in order to avoid confusion with these Action inherited methods.
 * 
 * - A Transform test-only method name SHOULD preferably use the "is", "has" prefixes followed by a noun or adjective.
 * When these prefixes are unsuitable, the method name SHOULD prefer a 3rd person indicative mood verb. For negative
 * tests, replace "is" with "isNot", "has" with "hasNo", and prepend "not" in other cases. Examples: "isMin", "isOneOf",
 * "isNotOneOf", "hasLength", "hasNoPunctuation", "equals", "notEquals", "matchesRegex", "notMatchesRegex". 
 */
interface Transform extends Action, NoEffect {}