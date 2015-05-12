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

/*
 * This file exists only so your IDE will know how to treat short method calls (i.e. without $this->) in templates.
 * The actual implementations of the functions to follow are the same-named methods of class AbstractTemplate.
 * 
 * Due to naming convention differences, transformation is applied for ex. foo_bar_baz() maps to $this->fooBarBaz().
 * 
 * TODO: DOCUMENT.
 */
 
function tag($name, $params = null) {}
function encode_html($value) {}
function encode_js($value) {}
function echo_raw($value) {}
function echo_html($value) {}
function echo_js($value) {}
function render($templateId) {}
function import($templateId) {}