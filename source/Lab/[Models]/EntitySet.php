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
 * Implements an SQL-backed basic table model with optional local (in memory) cache for reads (writes are committed
 * immediately). Suitable as a helper for implementing DAL / repository classes. Avoid inheritance and prefer 
 * composition, because some methods (getBySql, getAllBySql) while flexible, are unsafe for direct use by random places
 * in your codebase.
 * 
 * IMPORTANT: Don't enable caching if you intend to add/set/remove entries in the same SQL table outside this model, or
 * maintain correct cache by invoking setCache() after write operations done to the table.
 * 
 * If you however want to do that, make sure to use clearCache() and setCache() accordingly to make sure your changes
 * are in sync with the cache. If unsure, leave the cache setting disabled, or you'll get inconsistent results.
 * 
 * TODO: Those cache management methods are awkward. Decouple caching from the model if a practical way is found (i.e.
 * without making things more complicated than the caller simply managing their own cache array & checks).
 */
class EntitySet {
	protected $encode; // (dict) => dict;
	protected $decode; // (dict) => dict;
	protected $encodeAll; // (list<dict>) => list<dict>;
	protected $decodeAll; // (list<dict>) => list<dict>;
	protected $useCache; // bool;
	protected $idFieldName; // string;
	protected $tableName; // string;
	
	/**
	 * A cache of entities.
	 * 
	 * @var array
	 */
	protected $cache = [];
	
	/**
	 * @var \Solver\Lab\SqlConnection
	 */
	protected $sqlConnection;
	
	/**
	 * For legacy reasons this constructor supports an alternative argument signature:
	 * 
	 * __construct($sqlConnection, $tableName, $idName = 'id', $useCache = false, $encode = null, $decode = null);
	 * 
	 * Move your code away to the new signature as the old one will be dropped in a future major release.
	 * 
	 * @param SqlConnection $sqlConnection
	 * 
	 * @param string $tableName
	 * 
	 * @param array $options
	 * dict...
	 * - idFieldName: string = 'id'; The field name that represents the (primary) key of the record.
	 * - useCache: bool = false; Pass true to allow local caching of "canonical" entities (i.e. SELECT * without joins).
	 * The only methods that may return cached results are getById() and getAllById(), and only if the $forUpdate flag
	 * was not set to true (it defaults to false).
	 * - encodeAll?: (dict[]) => dict[]; A function that takes a list of entity dicts and returns records "encoded" for
	 * storage in an SQL table (for ex. containers as JSON etc.).
	 * - decodeAll?: (dict[]) => dict[]; A function that takes a list of record dicts and returns entities "decoded" 
	 * from their SQL table representation to its canonical (usable) version.
	 * - decode?: (dict) => dict; Identical to decodeAll, but for one record. You can provide both, or either of them,
	 * depending on what you have implemented.
	 * - encode?: (dict) => dict; Identical to encodeAll, but for one entity. You can provide both, or either of them,
	 * depending on what you have implemented.
	 */
	public function __construct(SqlConnection $sqlConnection, $tableName, $options = null) {
		// Legacy signature? 
		if (is_string($options) || func_num_args() > 3) {
			$args = func_get_args();
			$idFieldName = $options;
			$options = [];
			if ($idFieldName !== null) $options['idFieldName'] = $idFieldName;
			if (isset($args[3])) $options['useCache'] = $args[3]; 
			if (isset($args[4])) $options['encode'] = $args[4]; 
			if (isset($args[5])) $options['decode'] = $args[5];
		}
		
		// Defaults.
		if (!isset($options)) $options = [];
		$options += [
			'idFieldName' => 'id',
			'useCache' => false,
			'encodeAll' => null,
			'decodeAll' => null,
			'encode' => null,
			'decode' => null,
		];
		
		if ($options['encode'] && !$options['encodeAll']) {
			$options['encodeAll'] = function ($entities) {
				$records = [];
				foreach ($entities as $entity) { $records[] = $this->encode->__invoke($entity); }
				return $records;
			};
		}
		
		if ($options['decode'] && !$options['decodeAll']) {
			$options['decodeAll'] = function ($records) {
				$entities = [];
				foreach ($records as $record) { $entities[] = $this->decode->__invoke($record); }
				return $entities;
			};
		}
		
		if ($options['encodeAll'] && !$options['encode']) {
			$options['encode'] = function ($entity) {
				$records = $this->encodeAll->__invoke([$entity]);
				return $records[0];
			};
		}
		
		if ($options['decodeAll'] && !$options['decode']) {
			$options['decode'] = function ($record) {
				$entities = $this->decodeAll->__invoke([$record]);
				return $entities[0];
			};
		}
				
		$this->sqlConnection = $sqlConnection;
		$this->tableName = $tableName;
		$this->idFieldName = $options['idFieldName'];
		$this->useCache = $options['useCache'];
		$this->encodeAll = $options['encodeAll'];
		$this->decodeAll = $options['decodeAll'];
		$this->encode = $options['encode'];
		$this->decode = $options['decode'];
	}
	
	/**
	 * Inserts a new $entity. If your backing SQL table has autoincrementing PK, omit the PK field in the row you pass.
	 * If not, you need to include it.
	 * 
	 * @param array $entity
	 * dict; Entity to insert.
	 * 
	 * @return string|number|null
	 * Id of the inserted record (autoincrement id if this is what your table uses).
	 */
	public function create($entity) {
		$encode = $this->encode;
		$this->sqlConnection->insert($this->tableName, $encode ? $encode($entity) : $entity);
		
		if (isset($entity[$this->idFieldName])) {
			$id = $entity[$this->idFieldName];
		} else {
			$id = $this->sqlConnection->getLastId();
			$entity[$this->idFieldName] = $id;
		}
		
		return $id;
	}
	
	/**
	 * @deprecated
	 * This is the old name for method add().
	 */
	public function add($entity) {
		return $this->create($entity);
	}
	
	/**
	 * Variation of getAllById() for one entry. See getAllById() for details.
	 *
	 * @param string $id
	 * @param bool $forUpdate
	 * @return array|null
	 * The entity dict, or null if there's no entity with that id.
	 */
	public function getById($id, $forUpdate = false) {
		$entities = $this->getAllById([$id], $forUpdate);
		return $entities ? $entities[0] : null;
	}
	
	/**
	 * @deprecated
	 * This is the old name for method getById().
	 */
	public function get($id) {
		return $this->getById($id);
	}
	
	/**
	 * Returns the entities with the given ids.
	 *
	 * @param string[] $idList
	 * List of entity ids.
	 * 
	 * @param bool $forUpdate
	 * Optional, default false. Pass true to skip cache (if enabled) and make the SQL select "FOR UPDATE", which
	 * provides additional guarantees during a transaction if you plan to update the entities.
	 * 
	 * @return array
	 * List of entity dicts. Note there's to correspondence to the list indexes for input ids and output entities. The
	 * order if the entities is undefined, and if there's no entity with a given id, it simply won't be present in the
	 * output (it won't be returned as an array item set to null).
	 */
	public function getAllById($idList, $forUpdate = false) {
		if ($this->useCache && !$forUpdate) {
			$cachedEntities = [];
			$idList2 = [];
			
			foreach ($idList as $id) {
				if (isset($this->cache[$id])) $cachedEntities[] = $this->cache[$id];
				else $idList2[] = $id;
			}
			
			$idList = $idList2;
			unset($idList2);
		}
		
		if ($sql = $this->sqlById($idList, $forUpdate)) {
			$entities = $this->getAllBySql($sql, null, true);
		} else {
			$entities = [];
		}
		
		if ($this->useCache && !$forUpdate) {
			return array_merge($cachedEntities, $entities);
		} else {
			return $entities;
		}
	}
	
	/**
	 * A variation of getAllBySql() for one entity. See getAllBySql() for details.
	 * 
	 * @param string $sql
	 * @param array $params
	 * @param bool $canonical
	 * @return array|null
	 * dict|null; An entity dict or null of there was no match.
	 */
	public function getBySql($sql, $params, $canonical = false) {
		$entities = $this->getAllBySqlInternal(true, $sql, $params, $canonical);
		return $entities ? $entities[0] : null;
	}
	
	/**
	 * A generic method for selecting multiple entities from the entity table (can't utilize cache for results).
	 * 
	 * Uses the supplied decoder functions and maintains internal cache consistency.
	 * 
	 * @param string $sql
	 * SQL select query, selecting rows from the entity table.
	 * 
	 * @param array $params
	 * Optionally, parameters to interpolate into the query.
	 * 
	 * @param bool $canonical
	 * Default false. A "canonical" result means basically you're doing "SELECT * FROM" without joins on the entity 
	 * table. Canonical results can be stored and reused in your entity cache (if enabled) for other get*() calls.
	 * 
	 * Do NOT enable this flag carelessly, because if you don't follow the above contract, you will corrupt your 
	 * entity cache.
	 * 
	 * @return array
	 * list<dict>; Zero or more entities.
	 */
	public function getAllBySql($sql, $params, $canonical = false) {
		return $this->getAllBySqlInternal(false, $sql, $params, $canonical = false);
	}
	
	/**
	 * Updates an entity. This particular method is intended to be used by passing the COMPLETE entity to update in the
	 * database. 
	 * 
	 * IMPORTANT: If you're using an encoder which packs several fields into one SQL column (say in JSON), passing a 
	 * partial entity in this method may result in fields being lost when a JSON entry is replaced by a partial JSON
	 * entry. Always pass in the full entity to ensure correctness of operation.
	 * 
	 * If you want to safely patch an entity with partial data, use operations like mapById(), instead.
	 * 
	 * @param array $entity
	 * Full entity dict to update.
	 */
	public function set($entity) {
		$encode = $this->encode;
		$this->sqlConnection->updateByPrimaryKey($this->tableName, $this->idFieldName, $encode ? $encode($entity) : $entity);
		
		// The combination of partial rows, encoders, decoders and so on make updating the cache after set() very 
		// tricky, so we just clear the cache for this entry after update to be on the safe side.
		if ($this->useCache && isset($this->cache[$entity[$this->idFieldName]])) {
			unset($this->cache[$entity[$this->idFieldName]]);
		}
	}
	
	/**
	 * Variation of mapAllById() for one entry. See mapAllById() for details.
	 * 
	 * If you throw from a map*() function, the transaction will be rolled back.
	 * 
	 * @param string $id
	 * @param \Closure $map
	 * @return int
	 */
	public function mapById($id, \Closure $map) {
		return $this->mapAllById([$id], $map);
	}
	
	/**
	 * This is a combined get*() and set*() operation in a transactionally safe way. You can use it to update entities
	 * from one value to another by passing in a mapping function that takes the old entity and returns the new one.
	 * 
	 * This allows safely applying partial updates to entities which can use advanced encoding techniques (like
	 * combining fields in JSON).
	 * 
	 * If you throw from a map*() function, the entire transaction will be rolled back (for all entities).
	 * 
	 * @param string[] $idList
	 * List of entity ids to transform.
	 * 
	 * @param \Closure $map
	 * (entity: dict) => dict; 
	 * 
	 * @return int
	 * Number of retrieved (and updated, if $map has modified them) entities.
	 */
	public function mapAllById($idList, \Closure $map) {
		$tableNameQ = $this->qIdent($this->tableName);
		
		if ($sql = $this->sqlById($idList, true)) {
			$this->mapAllBySql($sql, null, $map, true);
		} else {
			return 0;
		}
	}
	
	/**
	 * Variation of mapAllBySql() for one entry. See mapAllBySql() for details.
	 * 
	 * The definition of "one entity" here refers to that this method will fetch only the first entry is fetched from
	 * the result set, no matter how many results it contains (which depends entirely on your query).
	 * 
	 * If you throw from a map*() function, the transaction will be rolled back.
	 * 
	 * @param string $sql
	 * @param string $params
	 * @param \Closure $map
	 * @param bool $canonical
	 * @return int
	 */
	public function mapBySql($sql, $params, \Closure $map, $canonical = false) {
		return $this->mapAllBySqlInternal(true, $sql, $params, $map, $canonical);
	}
	
	/**
	 * This is a combined get*() and set*() operation in a transactionally safe way. You can use it to update entities
	 * from one value to another by passing in a mapping function that takes the old entity and returns the new one.
	 * 
	 * If your SQL query is "canonical" (see details on the $canonical parameter below), this allows safely applying
	 * partial updates to entities which can use advanced encoding techniques (like combining fields in JSON).
	 * 
	 * IMPORTANT: If you're using an encoder which packs several fields into one SQL column (say in JSON), passing a 
	 * partial entity in this method may result in fields being lost when a JSON entry is replaced by a partial JSON
	 * entry. If unsure, always pass in the full "canonical" entity to ensure correctness of operation.
	 * 
	 * If you throw from a map*() function, the entire transaction will be rolled back (for all entities).
	 * 
	 * @param string $sql
	 * SQL query selecting entity records. It's highly recommended to make this a "SELECT ... FOR UPDATE" to ensure
	 * data integrity within the transaction.
	 * 
	 * @param string $params
	 * Parameters to interpolate in the SQL query (or null not to use this feature).
	 * 
	 * @param \Closure $map
	 * (entity: dict) => dict; 
	 * 
	 * @param bool $canonical
	 * Default false. A "canonical" mapping means basically you're doing "SELECT * FROM" without joins on the entity 
	 * table and your $map result contains the same set of fields. Canonical results can be stored and reused in your
	 * entity cache (if enabled) for get*() calls.
	 * 
	 * Do NOT enable this flag carelessly, because if you don't follow the above contract, you will corrupt your 
	 * entity cache.
	 * 
	 * @return int
	 * Number of retrieved (and updated, if $map has modified them) entities.
	 */
	public function mapAllBySql($sql, $params, \Closure $map, $canonical = false) {
		return $this->mapAllBySqlInternal(false, $sql, $params, $map, $canonical);
	}
	
	/**
	 * Deletes the entity with the given id.
	 * 
	 * @param string $id
	 */
	public function deleteById($id) {
		$sql = 'DELETE FROM ' . $this->qIdent($this->tableName) . ' WHERE ' . $this->qIdent($this->idFieldName) . ' = ?';
		$this->sqlConnection->query($sql, [$id]);
		
		if (isset($this->cache[$id])) unset($this->cache[$id]);
	}
	
	/**
	 * @deprecated
	 * This is the old name for method getById().
	 */
	public function remove($id) {
		return $this->deleteById($id);
	}
	
	/**
	 * Clears the local in-memory cache for a given entry (if the entry is in cache), or all entries.
	 * 
	 * If you have enabled caching, do this if you mutate the entities in this collection outside this class (i.e. by
	 * direct SQL calls for example). 
	 * 
	 * @param string $id
	 * Optional (default = null). Pass an id value to clear the cache for a specific entry, or pass null to clear all
	 * entries.
	 */
	public function clearCache($id = null) {
		if ($id === null) {
			$this->cache = [];
		} else {
			if (isset($this->cache[$id])) unset($this->cache[$id]);
		}
	}
	
	/**
	 * Sets the local in-memory cache for a given row to the passed value (if you know it has been modified in this
	 * way externally).
	 * 
	 * @param string $entity
	 * A full row dict.
	 */
	public function setCache($entity) {
		if ($this->useCache) {
			$this->cache[$entity[$this->idFieldName]] = $entity;
		}
	}
	
	/**
	 * Shortcut; used a lot.
	 */
	protected function qIdent($ident) {
		return $this->sqlConnection->quoteIdentifier($ident);
	}
	
	protected function mapAllBySqlInternal($firstOnly, $sql, $params, \Closure $map, $canonical = false) {
		$count = false;
		
		// TODO: The get/set loop should stream for large number of results, to avoid going out of RAM.
		$this->sqlConnection->transactional(function () use ($firstOnly, $sql, $params, $map, & $count, $canonical) {
			$entities = $this->getAllBySqlInternal($firstOnly, $sql, $params, $canonical);
			$count = count($entities);
			
			foreach ($entities as $entity) {
				$mappedEntity = $map($entity);
				if ($mappedEntity === null) throw new \Exception('Map function did not return an entity.');
				
				$this->set($mappedEntity);
				
				if ($this->useCache && $canonical) {
					$this->setCache($entity);
				}
			}
		});
		
		return $count;
	}
	
	protected function getAllBySqlInternal($firstOnly, $sql, $params, $canonical) {
		$sqlConnection = $this->sqlConnection;
		
		$resultSet = $sqlConnection->query($sql, $params);
		
		if ($firstOnly) {
			$records = [$resultSet->fetchOne()];
		} else {
			$records = $resultSet->fetchAll();
		}
		
		if ($this->decodeAll) {
			$decodeAll = $this->decodeAll;
			$entities = $decodeAll($records);
		}
		
		// Canonical entities, we can cache them.
		if ($this->useCache && $canonical) foreach ($entities as $entity) {
			$this->setCache($entity);
		}
		
		// Non-canonical entities, we just delete their cache (if id is present) in order to avoid consistency issues.
		if ($this->useCache && !$canonical) foreach ($entities as $entity) {
			if (isset($entity[$this->idFieldName])) $this->clearCache($entity[$this->idFieldName]);
		}
		
		return $entities;
	}
	
	protected function sqlById($idList, $forUpdate) {
		$count = count($idList);		
		if ($count == 0) return null;
			
		list($tableNameQ, $idFieldNameQ) = $this->qIdent([$this->tableName, $this->idFieldName]);
		$idListQ = $this->sqlConnection->quoteValue($idList);
		
		$sql = "SELECT * FROM $tableNameQ WHERE ";
		
		if ($count == 1) {
			$sql .= "$idFieldNameQ = $idListQ[0]";
		} else {
			$sql .= "$idFieldNameQ IN (" . implode(', ', $idListQ) . ")";
		}
		
		if ($forUpdate) $sql .= ' FOR UPDATE';
		
		return $sql;
	}
}