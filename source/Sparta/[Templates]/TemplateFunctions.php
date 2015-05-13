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
 * This file won't be loaded by PHP at runtime. It exists, so your IDE will know how to treat short method calls (i.e.
 * without $this->) in templates. The actual code invoked is a correspondingly named method of class AbstractTemplate.
 * 
 * Due to naming convention differences, transformation is applied for ex. function foo_bar_baz() maps to method 
 * AbstractTemplate::fooBarBaz().
 * 
 * TODO: DOCUMENT.
 */
 
/**
 * @see \Solver\Sparta\AbstractTemplate::tag()
 */
function tag($name, $params = null) {}

/**
 * @see \Solver\Sparta\AbstractTemplate::autoencode()
 */
function autoencode($format) {}

/**
 * @see \Solver\Sparta\AbstractTemplate::encodeHtml()
 */
function encode_html($value) {}

/**
 * @see \Solver\Sparta\AbstractTemplate::encodeJson()
 */
function encode_json($value) {}

/**
 * @see \Solver\Sparta\AbstractTemplate::echoRaw()
 */
function echo_raw($value) {}

/**
 * @see \Solver\Sparta\AbstractTemplate::echoHtml()
 */
function echo_html($value) {}

/**
 * @see \Solver\Sparta\AbstractTemplate::echoJson()
 */
function echo_json($value) {}

/**
 * @see \Solver\Sparta\AbstractTemplate::render()
 */
function render($templateId) {}

/**
 * @see \Solver\Sparta\AbstractTemplate::import()
 */
function import($templateId) {}