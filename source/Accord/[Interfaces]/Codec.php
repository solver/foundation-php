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
 * Combines two "opposite" transforms into one interface.
 * 
 * Methods encode(), decode() have the same semantics as Transform::apply().
 * 
 * You must respect the following contract:
 * 
 * - All Codec implementations MUST respect the contract rules specified for the Transform interface.
 * 
 * - When method "decode()" is fed the output of "encode()", its output SHOULD semantically match the input of
 * "encode()", i.e. the transforms should be symmetric. There's no requirement for a binary match, but it should be
 * sufficient to the semantics of your data domain, i.e. it's acceptable to model lossy transforms with this interface,
 * for example encoding a bitmap stream to a JPEG stream and decoding it back to a similar bitmap stream.
 */
interface Codec {
	public function encode($value, ErrorLog $log, $path = null);
	public function decode($value, ErrorLog $log, $path = null);
}