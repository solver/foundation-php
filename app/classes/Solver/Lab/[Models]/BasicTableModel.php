<?php
namespace Solver\Lab;

/**
 * Implements an SQL-backed basic table model with optional local (in memory) cache for reads (writes are committed
 * immediately).
 * 
 * IMPORTANT: Don't enable caching if you intend to add/set/remove entries in the same SQL table outside this model.
 * 
 * If you however want to do that, make sure to use clearCache() and setCache() accordingly to make sure your changes
 * are in sync with the cache. If unsure, leave the cache setting disabled, or you'll get inconsistent results.
 * 
 * TODO: Those cache management methods are awkward. Decouple caching from the model if a practical way is found (i.e.
 * without making things more complicated than the caller simply managing their own cache array & checks).
 */
class BasicTableModel {
	/**
	 * A cache of decoded (or un-encoded, same deal) rows.
	 * 
	 * @var array
	 */
	protected $cache = [];
	
	protected $encoder;
	
	protected $decoder;
	
	/**
	 * @var \Solver\Lab\SqlConnection
	 */
	protected $sqlConnection;
	
	protected $useCache;
	
	protected $idName;
	
	protected $tableName;
	
	/**
	 * @param \Solver\Lab\SqlConnection $sqlConnection
	 * @param string $idName
	 * Optional (default = 'id'). The field name that represents the (primary) key of the record.
	 * 
	 * @param unknown $useCache
	 * Optional (default = null). Pass true to allow local caching of records.
	 *
	 * @param unknown $encoder
	 * Optional (default = null). Pass a function that takes a row and returns a modified row, "encoded" for storage in 
	 * an SQL table.
	 *
	 * @param \Closure $decoder
	 * Optional (default = null). Pass a function that takes a row and returns a modified row, "decoded" from its SQL
	 * table representation to its canonical (usable) version.
	 */
	public function __construct(SqlConnection $sqlConnection, $tableName, $idName = 'id', $useCache = false, \Closure $encoder = null, $decoder = null) {
		$this->sqlConnection = $sqlConnection;
		$this->tableName = $tableName;
		$this->idName = $idName;
		$this->useCache = $useCache;
		$this->encoder = $encoder;
		$this->decoder = $decoder;
	}
	
	/**
	 * Inserts a new $row. If your backing SQL table has autoincrementing PK, omit the PK field in the row you pass. If
	 * not, you need to include it.
	 * 
	 * @param array $record
	 * Row dict to insert.
	 * 
	 * @return string|null
	 * Autoincrement id of the inserted record (if this is what your table uses).
	 */
	public function add($row) {
		$encoder = $this->encoder; // TRICKY: Calling $this->encoder() directly fails for closures.
		$this->sqlConnection->insert($this->tableName, $encoder ? $encoder($row) : $row);
		
		if (isset($row[$this->idName])) {
			$id = $row[$this->idName];
		} else {
			$id = $this->sqlConnection->getLastId();
			$row[$this->idName] = $id;
		}
		
		return $id;
	}
	
	/**
	 * Returns the row with the given id.
	 * 
	 * @param string $id
	 * 
	 * @return array
	 * The row dict, or false if there's no row with that id.
	 */
	public function get($id) {
		if (isset($this->cache[$id])) return $this->cache[$id];
		
		$sql = 'SELECT * FROM ' . $this->sqlConnection->quoteIdentifier($this->tableName) . ' WHERE ' . $this->sqlConnection->quoteIdentifier($this->idName) . ' = ?';
		$row = $this->sqlConnection->query($sql, [$id])->fetchOne();
		
		if ($row !== false && $this->decoder) {
			// TRICKY: Calling $this->decoder() directly fails for closures.
			$decoder = $this->decoder;
			$row = $decoder($row);
		}
		
		if ($this->useCache) $this->cache[$id] = $row;
		
		return $row;
	}
	
	/**
	 * Updates a row. You can pass a partial row (only some fields present) and only those fields will be updated, 
	 * however the id field is required at all times).
	 * 
	 * IMPORTANT: If you're using an encoder which packs several fields into one SQL column (say in JSON), not passing
	 * all fields packed together may result in fields being lost when a single JSON entry is replaced by the partial 
	 * JSON entry your partial row is encoded to. The safe option with such encoders is to always pass a row with all
	 * fields in it (no partials), unless you're sure which combinations are safe.
	 * 
	 * @param array $row
	 * Row dict (full or partial) to update.
	 */
	public function set($row) {
		$encoder = $this->encoder; // TRICKY: Calling $this->encoder() directly fails for closures.
		$this->sqlConnection->updateByPrimaryKey($this->tableName, $this->idName, $encoder ? $encoder($row) : $row);
		
		// The combination of partial rows, encoders, decoders and so on make updating the cache after set() very 
		// tricky, so we just clear the cache for this entry after update to be on the safe side.
		if ($this->useCache && isset($this->cache[$row[$this->idName]])) {		
			// TODO: Check if there's a safe way to update the cache taking all the particulars into account.
			unset($this->cache[$row[$this->idName]]);
		}
	}
	
	/**
	 * Deletes the row with the given id.
	 * 
	 * @param string $id
	 */
	public function remove($id) {
		$sql = 'DELETE FROM ' . $this->sqlConnection->quoteIdentifier($this->tableName) . ' WHERE ' . $this->sqlConnection->quoteIdentifier($this->idName) . ' = ?';
		$this->sqlConnection->query($sql, [$id]);
		
		if ($this->useCache) $this->cache[$id] = false;
	}
	
	/**
	 * Clears the local in-memory cache for a given entry (if the entry is in cache), or all entries.
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
	 * @param string $row
	 * A full row dict.
	 */
	public function setCache($row) {
		if ($this->useCache) {
			$this->cache[$row[$this->idName]] = $row;
		}
	}
}