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
namespace Solver\Sidekick;

class Schema {
	protected $tables = [];
	protected $rels = [];
	
	function addTable(Table $table) {
		$tableData = $table->render();		
		$this->tables[$tableData['name']] = $tableData;
		
		return $this;
	}
	
	function addLink(Link $link) {
		$linkData = $link->render();
		
		$index = function ($namespace, $hasMany, $localTableName, $localColumnName, $foreignTableName, $foreignColumnName, $junctionTableName) {
			$rel = [
				'namespace' => $namespace,
				'hasMany' => $hasMany,
				'junctionTableName' => $junctionTableName,
				'foreignTableName' => $foreignTableName,
				'localColumnName' => $localColumnName, // TODO: Verify if exists.
				'foreignColumnName' => $foreignColumnName, // TODO: Verify if exists.
			];
			
			if (!isset($this->tables[$junctionTableName])) {
				throw new \Exception('Table with public name "' . $junctionTableName . '" is not defined.');
			}
			
			if (!isset($this->tables[$localTableName])) {
				throw new \Exception('Table with public name "' . $localTableName . '" is not defined.');
			}
			
			if (!isset($this->tables[$foreignTableName])) {
				throw new \Exception('Table with public name "' . $foreignTableName . '" is not defined.');
			}
						
			if (!isset($this->rels[$localTableName])) {
				$this->rels[$localTableName] = [];
			}
			
			$this->rels[$localTableName][$namespace] = $rel;
		};
		
		$junctionTableName = $junction['tableName'];
		
		if ($linkData['first']['namespace']) {
			$namespace = $linkData['first']['namespace'];
			$hasMany = $linkData['firstHasMany'];
			$localTableName = $linkData['first']['tableName'];
			$localColumnName = $linkData['first']['columnName'];
			$foreignTableName = $linkData['second']['tableName'];
			$foreignColumnName = $linkData['second']['columnName'];
			$index($namespace, $hasMany, $localTableName, $localColumnName, $foreignTableName, $foreignColumnName, $junctionTableName);
		} else {
			$namespace = $linkData['second']['namespace'];
			$hasMany = $linkData['secondHasMany'];
			$localTableName = $linkData['second']['tableName'];
			$localColumnName = $linkData['second']['columnName'];
			$foreignTableName = $linkData['first']['tableName'];
			$foreignColumnName = $linkData['first']['columnName'];
			$index($namespace, $hasMany, $localTableName, $localColumnName, $foreignTableName, $foreignColumnName, $junctionTableName);
		}
		
		return $this;
	}
	
	function render() {
		return get_object_vars($this);
	}
}