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
namespace Solver\Lab;

/**
 * TODO: PHPDoc.
 * 
 * TODO: Due to time constraints some features got cut (this concerns the entire component, not just this interface):
 * - The "auto boolean" feature in dicts (intended only for reading HTTP fields) is always on, but should be optional.
 * - Unified format for custom error overrides.
 * - Ability to compile formats to optimized PHP code.
 * 
 * TODO: Add additional constraints on this interface (or provide as separate Format derived interfaces):
 * - Method useError(...$errorList) /or similar/ to replace the errors produced by a format (and its subformats) with a
 * custom list of errors.
 * - Explicit acknowledgement a format is cloneable (either a base requirement or as interface CloneableFormat).
 * - An interface providing standard ->bind(callable) for custom tests and filters (currently implemented in
 * AbstractFormat as two methods test() and filter() but the differentiation is pointless).
 * - An acknowledgement that the callable format for bind() and the format->extract have the same exact signature and
 * behavior and should be interchangeable (so we can pass format to bind or callable where Format is expected). Or 
 * alternatively provide a wrapper to expose one as the other.
 * 
 * TODO: Not related to this interface, but maybe we should have a standard factory interface and a standard factory
 * implementing it for common formats, allowing alternative implementations, which, for ex. generate PHP code, or
 * schemas in various notations (XML, JSON etc.).  
 * 
 * TODO: Convertor formats don't follow the Format contract, which requires that running formatted output through the
 * same output should yield the same output. We could split this into Convertor with the exact same interface as Format,
 * but lesser contract, and have Format extend Convertor as a marker interface to signify the extended contract. 
 * This would require careful rethinking where the typehint has to be Format and where Convertor. BTW Transform is a 
 * better name then Convertor maybe? Alternatively: convertors should be so designed that they can take follow the
 * Format semantics (i.e. a string to list convertor also takes a list and returns it unmodified, to preserve contract).
 * In other words Format is idempotent (applying it once is same as applying it 10 times). f A = f (f A) = f B, where
 * A is raw input, and B is processed input. Idempotency doesn't apply for the error log, of course (feeding null won't
 * produce the same errors as the original raw invalid input did).
 * 
 * Idempotency ensures you can run a piece of valid input through a format without having to know if you did it before,
 * very useful when input might get re-re-re-extracted a few times as it jumps scopes, and one scope doesn't trust the
 * one giving it the info.
 */
interface Format {
	/**
	 * Tries to extract the canonical representation of the data format from $value (or null if $value doesn't contain
	 * valid data in the required format).
	 * 
	 * @param mixed $value
	 * Value to filter and validate.
	 * 
	 * @param \Solver\Lab\ErrorLog $log
	 * Any errors will be logged here (valid values never log errors, invalid values always log 1 or more errors).
	 * 
	 * @param string $path
	 * Optional (default = null). An optional path (base) to log the errors at. 
	 * 
	 * @return mixed
	 * The canonical (and valid) representation of the data, or null if no such representation could be extracted.
	 */
	public function extract($value, ErrorLog $log, $path = null);
}