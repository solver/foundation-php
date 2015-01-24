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
 * Used for a dispatcher component (page handler, router) to signal the dispatcher (up the stack) that the currently
 * processed HTTP request should end with the HTTP status as specified in the exception code (i.e. code 404 for not
 * found and so on).
 * 
 * IMPORTANT: Legacy behavior for code = 0, which we're leaving in for B.C. for now:
 * 
 * If the code is left at the default 0, the signal should be interpreted as: processing should stop without further
 * output (the response has been handled). This can be used by components that handle the error display on their own,
 * but want to make sure processing stops afterwards.
 */
class PageException extends Exception {}
?>