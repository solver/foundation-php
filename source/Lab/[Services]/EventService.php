<?php
/*
 * Copyright (C) 2011-2014 Solver Ltd. All rights reserved.
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
 * Supports infrastructure for extensible, searchable application level events.
 * 
 * Suitable for event sourcing, logging and other similar purposes.
 */
class EventService {
	/**
	 * @var \Solver\Lab\SqlConnection
	 */
	protected $sqlConnection;
	
	/**
	 * @var string
	 */
	protected $tableName;
	
	/**
	 * @var array
	 */
	protected $fieldNames;
	
	/** 
	 * @var array
	 */
	protected $extensions;
	
	/**
	 * @param SqlConnection $sqlConnection
	 * 
	 * @param string $tableName
	 * An SQL table name having these required fields (and any other you need, see $fields):
	 * 		
	 * - id: bigint, unsigned, autoincrement, PK
	 * - type: enum("success", "info", "warning", "error") or if not available, varchar(7), binary, UTF8/ASCII.
	 * - code: 	varchar(255 or larger), binary, UTF8, with non-unique index
	 * - dateCreated: datetime, with non-unique index
	 * - details: nullable, mediumtext /or longtext if you must/, UTF8, stores arbitrary fields as JSON
	 * 
	 * @param string $fieldNames
	 * A list of custom field names that are present in the event table additional to the required fields. Those fields
	 * will be used to store root-level "details" fields instead of being packed into the "details" column as JSON.
	 * 
	 * This allows you to have an index and perform a search based on a "details" root-level field.
	 * 
	 * All custom fields must be prefixed with "details_" in your SQL schema (not while being listed) to avoid collision
	 * with the standard event fields (i.e. if you specify field name "foo" your SQL table must have a column
	 * "details_foo").
	 * 
	 * If you want specifying the field in $details to be optional, make the SQL field optional
	 * Optional fields must be nullable, with NULL as their default value.
	 * 
	 * @param array $extensions
	 * Allows you to map more details fields to actual table fields by joining tables based on your event code.
	 * 
	 * Field names must be unique across a combination of joined tables to avoid ambiguity. Like $fieldNames, the actual
	 * columns in your SQL tables must be prefixed with "details_", and fields must be nullable with default NULL if 
	 * they're optional.
	 * 
	 * If you don't need a searchable index on a field, you don't need to create an extension in order to store it.
	 * 
	 * The extension tables must have an "id" field (no need to list it, it's implied), with the following setup:
	 * 
	 * - Primary key
	 * - Bigint, unsigned, autoincrement, PK (like main table's id)
	 * - Foreign key to the PK "id" of the main table (cascade on delete and update).
	 * 
	 * Format of the $extensions parameter:
	 * 
	 * <code>
	 * [
	 * 		$code => [ $table => [$field, $field, $field...], $table => [$field, $field, $field...] ],
	 * 		$code => [ $table => [$field, $field, $field...], $table => [$field, $field, $field...] ],
	 * 		...
	 * ]
	 * </code>
	 * 
	 * Optional fields must be nullable, with NULL as their default value.
	 */
	public function __construct(SqlConnection $sqlConnection, $tableName, $fieldNames = null, $extensions = null) {
		// TODO: Add assertion checks for duplicate field names across extension combinations.
		
		$this->sqlConnection = $sqlConnection;
		$this->tableName = $tableName;
		$this->fieldNames = $fieldNames;
		$this->extensions = $extensions;
	}
	
	/**
	 * Writes a new "success" level event. You should use this for events that signify actions taken during normal 
	 * operations, which change persistent state, and are therefore significant when using events to rebuild state.
	 * 
	 * If the event is not significant to system's persistent state, use addInfo().
	 * 
	 * IMPORTANT: Don't confuse the intended operation "success" here with user-level "success" (i.e. did the user log
	 * in properly or not). Success here signifies the system operates normally, even if the user is prevented from
	 * completion, say due to bad input. 
	 * 
	 * @param string $code
	 * A string to identify what's happening. By convention, most service use their full method name as the code string,
	 * i.e. __METHOD__ (for ex. "Blitz\FooService::doAction").
	 * 
	 * @param array $details
	 * A dict of arbitrary fields for the event. If a root field name matches one of the specified dedicated SQL table
	 * fields in $fieldNames or $extensions (during contruction) they'll be stored in that field. Otherwise they'll be
	 * serialized as JSON in the "details" field.
	 */
	public function addSuccess($code, $details) {
		$this->addEvent('success', $code, $details);
	}	
	
	/**
	 * Writes a new "info" level event. You should use this for neutral status and diagnostic information that is
	 * not required in order to reconstruct the system state.
	 * 
	 * @param string $code
	 * A string to identify what's happening. By convention, most service use their full method name as the code string,
	 * i.e. __METHOD__ (for ex. "Blitz\FooService::doAction").
	 * 
	 * @param array $details
	 * A dict of arbitrary fields for the event. If a root field name matches one of the specified dedicated SQL table
	 * fields in $fieldNames or $extensions (during contruction) they'll be stored in that field. Otherwise they'll be
	 * serialized as JSON in the "details" field.
	 */
	public function addInfo($code, $details) {
		$this->addEvent('info', $code, $details);
	}	
	
	/**
	 * Writes a new "warning" level event. You should use this for problems that should be noted by the application
	 * maintaners, but aren't hard errors (for example slow processing or other non-fatal conditions).
	 * 
	 * Another use for warnings might be signs of intrusion or intrustion attempts, hacking, and other events that a
	 * maintainer should be aware of.
	 * 
	 * @param string $code
	 * A string to identify what's happening. By convention, most service use their full method name as the code string,
	 * i.e. __METHOD__ (for ex. "Blitz\FooService::doAction").
	 * 
	 * @param array $details
	 * A dict of arbitrary fields for the event. If a root field name matches one of the specified dedicated SQL table
	 * fields in $fieldNames or $extensions (during contruction) they'll be stored in that field. Otherwise they'll be
	 * serialized as JSON in the "details" field.
	 */
	public function addWarning($code, $details) {
		$this->addEvent('warning', $code, $details);
	}	
	
	/**
	 * Writes a new "error" level event. Use this for unexpected events that require quick intervention by the 
	 * application maintainers (crashes, inconsistent state, unreachabe database and other unexpected conditions).
	 * 
	 * @param string $code
	 * A string to identify what's happening. By convention, most service use their full method name as the code string,
	 * i.e. __METHOD__ (for ex. "Blitz\FooService::doAction").
	 * 
	 * @param array $details
	 * A dict of arbitrary fields for the event. If a root field name matches one of the specified dedicated SQL table
	 * fields in $fieldNames or $extensions (during contruction) they'll be stored in that field. Otherwise they'll be
	 * serialized as JSON in the "details" field.
	 */
	public function addError($code, $details) {
		$this->addEvent('error', $code, $details);
	}
	
	/**
	 * Returns the earliest event matching the criteria.
	 * 
	 * @param string $type
	 * String type to filter by (one of: "success", "info", "warning", "error"), or null (not to filter by type).
	 * 
	 * @param string $code
	 * String code to filter by, or null (not to filter by code).
	 * 
	 * @param string $details
	 * Optional (default = null). Dict of "details" fields the event should match. Refers to custom fields specified via
	 * $fieldNames or $extensions during construction, because fields stored as JSON aren't searchable.
	 * 
	 * @param string $dateMin
	 * Optional (default = null). Pass a UNIX timestamp and you'll filter out any events that are older than this. 
	 * 
	 * @param string $dateMax
	 * Optional (default = null). Pass a UNIX timestamp and you'll filter out any events that are more recent than this.
	 * 
	 * @return array
	 * Returns a dict of event fields or null if there's no matching event.
	 */
	public function getFirstEvent($type = null, $code, $details = null, $dateMin = null, $dateMax = null) {
		$events = $this->getFilteredEvents(1, true, $type, $code, $details, $dateMin, $dateMax);
		return $events ? $events[0] : null;
	}
	
	/**
	 * Gets the most recent event matching the criteria. 
	 * 
	 * @param string $type
	 * String type to filter by (one of: "success", "info", "warning", "error"), or null (not to filter by type).
	 * 
	 * @param string $code
	 * String code to filter by, or null (not to filter by code).
	 * 
	 * @param string $details
	 * Optional (default = null). Dict of "details" fields the event should match. Refers to custom fields specified via
	 * $fieldNames or $extensions during construction, because fields stored as JSON aren't searchable.
	 * 
	 * @param string $dateMin
	 * Optional (default = null). Pass a UNIX timestamp and you'll filter out any events that are older than this. 
	 * 
	 * @param string $dateMax
	 * Optional (default = null). Pass a UNIX timestamp and you'll filter out any events that are more recent than this.
	 * 
	 * @return array
	 * Returns a dict of event fields or null if there's no matching event.
	 */
	public function getLastEvent($type = null, $code, $details = null, $dateMin = null, $dateMax = null) {
		$events = $this->getFilteredEvents(1, false, $type, $code, $details, $dateMin, $dateMax);
		return $events ? $events[0] : null;
	}
	
	/**
	 * Returns up to $count earliest events matching the criteria, sorted by date (ascending).
	 * 
	 * @param string $type
	 * String type to filter by (one of: "success", "info", "warning", "error"), or null (not to filter by type).
 	 * 
	 * @param string $count
	 * How many events to return.
	 * 
	 * @param string $code
	 * String code to filter by, or null (not to filter by code).
	 * 
	 * @param string $details
	 * Optional (default = null). Dict of "details" fields the event should match. Refers to custom fields specified via
	 * $fieldNames or $extensions during construction, because fields stored as JSON aren't searchable.
	 * 
	 * @param string $dateMin
	 * Optional (default = null). Pass a UNIX timestamp and you'll filter out any events that are older than this. 
	 * 
	 * @param string $dateMax
	 * Optional (default = null). Pass a UNIX timestamp and you'll filter out any events that are more recent than this.
	 * 
	 * @return array
	 * Returns a list of events (each a dict of event fields) or an empty array if there are no matching events.
	 */
	public function getManyFirstEvents($count, $type = null, $code, $details = null, $dateMin = null, $dateMax = null) {
		return $this->getFilteredEvents($count, true, $type, $code, $details, $dateMin, $dateMax);
	}
	
	/**
	 * Gets up to $count most recent events matching the criteria, sorted by date (descending). 
	 * 
	 * @param string $type
	 * String type to filter by (one of: "success", "info", "warning", "error"), or null (not to filter by type).
	 * 
	 * @param string $count
	 * How many events to return.
	 * 
	 * @param string $code
	 * String code to filter by, or null (not to filter by code).
	 * 
	 * @param string $details
	 * Optional (default = null). Dict of "details" fields the event should match. Refers to custom fields specified via
	 * $fieldNames or $extensions during construction, because fields stored as JSON aren't searchable.
	 * 
	 * @param string $dateMin
	 * Optional (default = null). Pass a UNIX timestamp and you'll filter out any events that are older than this. 
	 * 
	 * @param string $dateMax
	 * Optional (default = null). Pass a UNIX timestamp and you'll filter out any events that are more recent than this.
	 * 
	 * @return array
	 * Returns a list of events (each a dict of event fields) or an empty array if there are no matching events.
	 */
	public function getManyLastEvents($count, $type = null, $code, $details = null, $dateMin = null, $dateMax = null) {
		return $this->getFilteredEvents($count, false, $type, $code, $details, $dateMin, $dateMax);
	}
	
	protected function addEvent($type, $code, $details) {
		$row = [];
		$row['type'] = $type;
		$row['code'] = $code;
		$row['dateCreated'] = SqlExpression::toDatetime(time());
				
		// Dedicated "details" fields.
		if ($this->fieldNames) foreach ($this->fieldNames as $fieldName) {
			if (isset($details[$fieldName])) {
				$row['details_' . $fieldName] = $details[$fieldName];
				unset($details[$fieldName]); 
			}
		}
		
		$extRows = [];
		
		// Extension dedicated "details" fields.
		if (isset($this->extensions[$code])) foreach ($this->extensions[$code] as $tableName => $fieldNames) {
			foreach ($fieldNames as $fieldName) {
				if (isset($details[$fieldName])) {
					$extRows[$tableName]['details_' . $fieldName] = $details[$fieldName];
					unset($details[$fieldName]);
				}	
			}	
		}
		
		// Dynamic (JSON) fields.
		if ($details) $row['details'] = \json_encode($details, \JSON_UNESCAPED_UNICODE);
		
		$this->sqlConnection->transactional(function () use (& $row, & $extRows) {
			$this->sqlConnection->insert($this->tableName, $row);
			$id = $this->sqlConnection->getLastId();
			
			foreach ($extRows as $extTableName => $extRow) {
				$extRow['id'] = $id;
				$this->sqlConnection->insert($extTableName, $extRow);
			}
		});
	}
	
	protected function getFilteredEvents($count, $isAsc, $type, $code, $details, $dateMin, $dateMax) {
		// TODO: Add an assertion checking for looking up $details fields that have no assigned dedicated table column.
		
		$sql = 'SELECT * FROM ' . $this->tableName . ' ';
		
		if (isset($this->extensions[$code])) foreach ($this->extensions[$code] as $tableName => $fieldNames) {
			$sql  .= 'JOIN ' . $tableName . ' ON ' . $tableName . '.id = ' . $this->tableName . '.id ';
		}
		
		if ($details) {
			foreach ($details as $fieldName => $fieldVal) {
				$sqlFilter['details_' . $fieldName] = $fieldVal;
			}
		} else {
			$sqlFilter = [];
		}
	
		if ($type !== null)	$sqlFilter['type'] = $type;
		if ($code !== null)	$sqlFilter['code'] = $code;
		
		if ($dateMin !== null && $dateMax !== null) {
			$sqlFilter['dateCreated'] = ['BETWEEN', [SqlExpression::toDatetime($dateMin), SqlExpression::toDatetime($dateMax)]];
		} else if ($dateMin !== null) {
			$sqlFilter['dateCreated'] = ['>=', SqlExpression::toDatetime($dateMin)];
		} else if ($dateMax !== null) {
			$sqlFilter['dateCreated'] = ['<=', SqlExpression::toDatetime($dateMax)];
		}
				
		if ($sqlFilter) $sql .= 'WHERE ' . SqlExpression::boolean($this->sqlConnection, $sqlFilter, 'AND') . ' ';
		
		$sql .= 'ORDER BY ' . $this->tableName . '.id ' . ($isAsc ? 'ASC' : 'DESC') . ' LIMIT ' . (int) $count;
				
		$rows = $this->sqlConnection->query($sql)->fetchAll();
		
		foreach ($rows as & $row) {
			$row['dateCreated'] = SqlExpression::fromDatetime($row['dateCreated']);
			if ($row['details'] !== null) {
				$row += \json_decode($row['details'], \JSON_OBJECT_AS_ARRAY);
			}
		}
		unset($row);
		
		return $rows;
	}
}