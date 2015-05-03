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
namespace Solver\Logging;

/**
 * Defines a readable in-memory log.
 * 
 * Components can pass these logs to each other as reports for their runtime activity.
 * 
 * TODO: Document method semantics.
 */
interface MemoryLog extends Log {
	function hasEvents($types = null);
	function getEvents($types = null);
}