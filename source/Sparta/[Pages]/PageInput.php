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
namespace Solver\Sparta;

use Solver\Lab\DataBox;

/**
 * Provides inputs (request, environment, server vars) to Page classes.
 * 
 * - See \Solver\Base\Util::getInputFromGobals() for details on the the data at keys: query, body, server, cookies, env.
 * - See Router and Dispatcher for details on the the data that may be added at keys: tail, vars, exception.
 */
class PageInput extends DataBox {}