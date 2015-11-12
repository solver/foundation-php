<?php
namespace Solver\Sql;

use Solver\Core;
use Solver\Dev;
use PDO;
use PDOException;

/**
 * Base class for PDO-based connection adapters.
 * 
 * Currently supports pgsql/mysql/sqlite. For comments on the public methods of Connection, see Connecton.
 * 
 * TODO: Refactor PDO driver specific code into the relevant extending class (so they're not just empty shells).
 * TODO: Before executing db operation set lastId to null and restore afterwards on success to avoid unknown state.
 * 
 * @author Stan Vassilev
 * @copyright 2012, Solver Ltd.
 */
abstract class PdoConnection implements Connection
{
	/**
	 * Stores reference to the PDO object used for connectivity.
	 *
	 * @var PDO
	 */
	protected $handle = null;
	
	
	/**
	 * Stores the configuration to open the connection with.
	 * 
	 * @var array
	 */
	protected $config;
	
	
	/**
	 * A finalized database can not be reopened (see close($finalize)).
	 * 
	 * @var bool 
	 */
	protected $finalized = false;
	
	
	/**
	 * Stores the driver name (mysql, sqlite etc.) for the current connection.
	 *
	 * @var string
	 */
	protected $serverName;
	
		
	/**
	 * Each transaction has unique id retrieved from this counter.
	 * 
	 * @var int
	 */
	protected $transId = 1;	
	
		
	/**
	 * Stack of transactions, each stack item is a hash with the following items:
	 * 
	 * tid => {transaction id}
	 * cnt => {contract name}
	 * cnr => {contract resolution name}
	 * isl => {isolation level}
	 * 
	 * @var int
	 */
	protected $transStack = array();
	
	
	/**
	 * Isolation levels for standard SQL and SQLite.
	 * 
	 * @var array 
	 */
	protected $transIsoLevels = array(
		'default' => array(
			'READ UNCOMMITTED' => 1,
			'READ COMMITTED' => 2,
			'REPEATABLE READ' => 3,
			'SERIALIZABLE' => 4,
		),
		'sqlite'=> array(
			'DEFERRED' => 1,
			'IMMEDIATE' => 2,
			'EXCLUSIVE' => 3
		)
	);
	
	
	/**
	 * Default isolation level for the connection (depends on the server).
	 * 
	 * TODO: Add config option to change the isolation level default (server config / table engine may override default).
	 * 
	 * @var int
	 */
	protected $transIsoLevelDefault;
	
	
	/**
	 * Transaction isolation level in use for the current transaction (if any).
	 * 
	 * @var int
	 */
	protected $transIsoLevelCurrent;
	
	
	/**
	 * Holds the flag for methods isDirty() / markClean(), notifying presence of unresolved rollback.
	 * 
	 * @var bool
	 */
	protected $dirty = false;
	
	
	/**
	 * Stores the proper valid last insert id for MySQL/SQLite. See getLastId().
	 * 
	 * @var int
	 */
	protected $lastId = null;
	
	
	/**
	 * Cache for getServerVer(true).
	 * 
	 * @var string
	 */
	protected $verString = null;
		
	
	/**
	 * Cache for getMaxPacketSize().
	 * 
	 * @var float
	 */
	protected $maxPacketSize = null;
	
	
	/**
	 * Cache for getServerVer(false).
	 * 
	 * @var float
	 */
	protected $verFloat = null;
	
	
	public function __construct(array $config)
	{
		$this->config = $config;
	}
	
	
	public function isOpen()
	{
		return $this->handle !== null;
	}
	
	
	public function canOpen()
	{
		return ($this->handle === null) && !$this->finalized;
	}
	
	public function open()
	{
		if ($this->handle !== null) $this->errorOpen('open');
		if ($this->finalized) $this->errorFinalized('open');
		 
		$params = array();
		
		// TODO: Add assertions for unknown or bad config values.
		
		if (isset($this->config['persistent']))  {
			$params[PDO::ATTR_PERSISTENT] = $this->config['persistent'];
		}
		
		try {
			switch (get_class($this)) {
				case 'Solver\Sql\PdoMysqlSession':
					$this->handle = new PDO('mysql:host='.$this->config['host'].
											(isset($this->config['port'])?':'.$this->config['port']:'').
											';dbname='.$this->config['database'], 
											$this->config['user'], 
											$this->config['password'],
											$params );
											
					$this->transIsoLevelDefault = 3; // Maps to READ_REPEATABLE, see $transIsoLevels.
					$this->serverName = 'mysql';
										
					break;
				case 'Solver\Sql\PdoPgsqlConnection':
					$this->handle = new PDO('pgsql:host='.$this->config['host'].
											(isset($this->config['port'])?':'.$this->config['port']:'').
											';dbname='.$this->config['database'], 
											$this->config['user'], 
											$this->config['password'],
											$params );
											
					$this->transIsoLevelDefault = 2; // Maps to READ_COMMITTED, see $transIsoLevels.
					$this->serverName = 'pgsql';
					
					break;
				case 'Solver\Sql\PdoSqliteConnection':
					$this->handle = new PDO('sqlite:'.$this->config['database'],
											null, 
											null,
											$params );		

					$this->transIsoLevelDefault = 1; // Maps to DEFERRED, see $transIsoLevels.
					$this->serverName = 'sqlite';
					
					break;
				default:
					// TODO: That's bad design and a temp compromise and will be factored out later on in a back-compat fashion.
					throw new \Exception('Class "' . get_class($this) . '" is not supported by PdoConnection.');
			}
			
			$this->handle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			
		} catch (PDOException $e) {
			
			throw new ConnectionException($e->getMessage(), $e->getCode());
		}
	}
	
	
	public function close($finalize = false)
	{
		if ($this->handle === null) $this->errorClosed('close');
		
		$this->handle = null; // The only way PDO would close the connection...
		if ($finalize) $this->finalized = true;
	}
	
	
	public function hasPendingStatements()
	{
		throw new Core\NotImplementedException('hasPendingStatements() not implemented');
	}
	
	
	public function query($query, $val = null)
	{
		if ($this->handle === null) $this->open();
		
		if (\func_num_args() > 1) $query = $this->quoteInto($query, $val);		
		$s = new PdoStatement($query, $this);
		$s->open();
		return $s;
	}
		
	public function queryOnDemand($query, $val = null)
	{
		if ($this->handle === null) $this->open();
		
		if (\func_num_args() > 1) $query = $this->quoteInto($query, $val);		
		return new PdoStatement($query, $this);
	}	

	public function execute($query, $val = null)
	{	
		if ($this->handle === null) $this->open();
		
		if (\func_num_args() > 1) $query = $this->quoteInfo($query, $val);
		
		if (Debug\LOG) {
			$t = microtime(true);		
		}
		
		try {
			$affectedRows =  $this->handle->query($query);
			if ($id = $this->handle->lastInsertId()) $this->lastId = $id;
		} catch (PDOException $e) {
			if (Debug\LOG) Dev::addQuery('exec', $query, microtime(true) - $t);
			throw new ConnectionException($e->getMessage());
		}
	
		if (Debug\LOG) Dev::addQuery('exec', $query, microtime(true) - $t);
		
		return $affectedRows;
	}		
	

	public function quoteVal($val)
	{
		if ($this->handle === null) $this->open();
		
		if (!is_array($val)) {
			return $val === null ? 'NULL' : $this->handle->quote($val);
		} else {
			$handle = $this->handle;
			
			foreach ($val as & $v) {
				$v =  $v === null ? 'NULL' : $handle->quote($v);
			}
			
			return $val;
		}
	}
	
	
	public function quoteIdent($ident)
	{
		// REVISE: Opening here is not required, but serverName is not set until open() After refactoring, remove next line.
		if ($this->handle === null) $this->open();
		
		switch ($this->serverName) {
			case 'mysql':
				if (!\is_array($ident)) {			
					return '`' . \str_replace(array('`', '.'), array('``', '`.`'), $ident).'`';
				} else {			
					foreach ($ident as & $i) {
						$i = '`' . \str_replace(array('`', '.'), array('``', '`.`'), $i) . '`';
					}
					return $ident;
				}
				break;
			case 'sqlite':
			case 'pgsql':
				if (!\is_array($ident)) {			
					return '"' . \str_replace(array('"', '.'), array('""', '"."'), $ident) . '"';
				} else {			
					foreach ($ident as & $i) {
						$i = '"' . \str_replace(array('"', '.'), array('""', '"."'), $i) . '"';
					}
					return $ident;
				}
				break;
			case 'mssql':
				if (!\is_array($ident)) {			
					return '[' . $ident . ']';
				} else {			
					foreach ($ident as & $i) {
						$i = '[' . $i . ']';
					}
					return $ident;
				}
				break;
			default:
				throw new Core\NotImplementedException('Server not supported.');
		}
	}
	
	
	public function quoteRow(array $rows) 
	{
		return \array_combine($this->quoteIdent(\array_keys($rows)), $this->quoteVal(\array_values($rows)));
	}
	
	
	public function quoteInto($expr, $val)
	{
		if (is_array($val)) {
			$val = $this->quoteVal($val);
			$expr = \explode('?', $expr);
			
			if (($ce = count($expr)) != count($val) + 1) {
				throw new ConnectionException('The number of values passed does not match the number of replace marks in the expression.');
			}
			
			$out = \reset($expr) . \reset($val) . \next($expr);
			
			for ($i = 2; $i < $ce; $i++) {
				$out .= \next($val).$expr[$i];
			}	

			return $out;
		} else {
			return \str_replace('?', $this->quoteVal($val), $expr);
		}
	}
	
	/**
	 * Returns the connection's driver name.
	 *
	 * @return string
	 */
	public function getServerName()
	{
		return $this->serverName;
	}
	
	
	public function getServerVersion($nativeString = false)
	{
		if ($this->handle === null) $this->open();
		
		if ($nativeString) {
			if ($this->verString === null) {
				$this->verString = $this->handle->getAttribute(PDO::ATTR_SERVER_VERSION);
			}
			
			return $this->verString;
		} else {
			if ($this->verFloat === null) {
				if ($this->verString === null) {
					$this->verString = $this->handle->getAttribute(PDO::ATTR_SERVER_VERSION);
				}
				
				\preg_match('/^(\d+)[.,]([\d,.]*)/', $this->verString, $seg);
				
				if (!count($seg)) {
					throw new ConnectionException('Can not parse server version to float: ' . $this->verString . '.');
				} else {
					$this->verFloat = (float) $seg[1].'.' . \preg_replace('/[^0-9]+/', '', $seg[2]);
				}
				
				return $verFloat;				
			}
			
			return $this->verFloat;
		}
	}
	
	
	public function getLastId($safeFetch = false) 
	{
		if ($this->serverName == 'mysql') {
			if ($this->handle === null) $this->open();
		
			// Null signifies non-initialized value or unknown state after db exception.
			// In this case handle gracefully by doing a direct query.
			
			if ($safeFetch || $this->lastId === null) {
				$res = $this->query('SELECT LAST_INSERT_ID()')->fetchOne(0);
				$this->lastId = $res;
			}
			
			return $this->lastId;
		} else if ($this->serverName == 'sqlite') {
			if ($this->handle === null) $this->open();
					
			return $this->query('SELECT last_insert_rowid()')->fetchOne(0);
		} else {
			throw new Core\NotApplicableException('Server "' . $this->serverName . '" does not provide last id.');
		}
	}
	
	
	/**
	 * For use by PdoStatement instances only!
	 * 
	 * TODO: Add a friend assertion.
	 * 
	 * @param bool $safeFetch
	 * @return int
	 */
	public function setLastId($val) 
	{
		$this->lastId = $val;
	}
	
	
	public function getMaxPacketSize() 
	{
		if ($this->serverName != 'mysql') {
			if ($this->handle === null) $this->open();
			
			if ($this->maxPacketSize === null) {
				$this->maxPacketSize = (int) $this->query('SHOW VARIABLES LIKE \'max_allowed_packet\'')->fetchOne('Value');
			}
			
			return $this->maxPacketSize;
		} else {
			throw new Core\NotImplementedException('Max packet size not implemented or server "' . $this->serverName . '".');
		}
	}

	
	public function begin($contract = 'full', $isolation = null)
	{
		if ($this->handle === null) $this->open();
		
		if ($contract === null) $contract = 'full';
		
		$isTop = !\count($this->transStack);			
		$tid = $this->transId++;

		/*
		 * Interpret the passed isolation level.
		 */
		
		$isoLevelKey = $this->serverName == 'sqlite' ? 'sqlite' : 'default';
		
		if ($isolation === null) {
			$isoLevel = $this->transIsoLevelDefault; 
		} else {
			if (isset($this->transIsoLevels[$isoLevelKey][$isolation])) {
				$isoLevel = $this->transIsoLevels[$isoLevelKey][\strtoupper($isolation)];
			} else {
				throw new ConnectionException('Isolation level "' . $isolation . '" is not recognized for server "' . $this->serverName . '".');
			}
		}
		
		/*
		 * Check if isolation level is compatible (equal or lower), if the transaction is nested.
		 */
		 
		if (!$isTop && $isoLevel > $this->transIsoLevelCurrent) {				
			$revLvl = \array_flip($this->transIsoLevels[$isoLevelKey]);				
			throw new ConnectionException('A nested transaction requested '.($isolation === null ? 'the default ' : '').
										'isolation level "'.$revLvl[$isoLevel].				
										'" but the outermost transaction used lower level "'.$revLvl[$this->transIsoLevelCurrent].'".'
										, 1);
		}
		
		/*
		 * Set isolation and start the transaction.
		 */
		
		if ($isTop) {
			if ($isolation === null) {
				$this->exec('BEGIN');
			} else {
				if ($this->serverName == 'mysql') {
					$this->exec('SET TRANSACTION ISOLATION LEVEL '.$isolation);
					$this->exec('BEGIN');
				} else { // Pgsql and sqlite.
					$this->exec('BEGIN '.$isolation);
				}
			}
			
			$this->transIsoLevelCurrent = $isoLevel;
		} else {
			switch (strtolower($contract)) {
				case 'full':					
					throw new ConnectionException('Can not fullfill transaction contract "full" in a nested transaction.', 1);
					break;
				
				case 'stored':
					switch ($this->serverName) {
						case 'mysql':
						case 'pgsql':
							$this->exec('SAVEPOINT phi'.$tid);								
							break;
						case 'sqlite': // save points not supported by sqlite
							throw new ConnectionException('Can not fullfill transaction contract "stored" for server "sqlite".');
							break;
						default:
							throw new Core\NotImplementedException('Transaction API not implemented for class "' . get_class($this) . '".');
					}
					break;
				
				case 'weak':
					if ($isTop) {
						$this->handle->beginTransaction();	
					}
					break;
				default:
					throw new ConnectionException('Unknown transaction contract name "' . $contract . '".');
					break;
			}	
		}
		
		$this->transStack[] = array(
			'tid' => $tid, // Transaction id.
			'cnt' => $contract, // Contract type.
			'cnr' => $isTop ? 'full' : $contract, // Actual contract resolution type.
			'isl' => $isolation, // Isolation type.
		);
		
		return $tid;			
	}
	
	
	public function commit($transId)
	{
		$trans = array_pop($this->transStack);
		
		if (!$trans) { // There is no opened transaction.
			throw new ConnectionException('Can not commit: no open transaction.');
		} else if ($trans['tid'] !== $transId) {
			$this->exec('ROLLBACK');
			$this->transStack = array();
			throw new ConnectionException('Can not commit: transaction id is not matching inner-most transaction. Stack rollback performed.');
		} else if ($this->dirty) {
			$this->exec('ROLLBACK');
			$this->transStack = array();
			throw new ConnectionException('Can not commit: state is dirty. Stack rollback performed.');
		} else {
			switch ($trans['cnr']) {
				case 'full':
					$this->exec('COMMIT');
					break;
				case 'stored':
					$this->exec('RELEASE SAVEPOINT phi'.$trans['tid']);
					break;
				// For contract 'weak' there are no actions to take.
			}
		}
	}

	
	public function rollback($transId)
	{
		$trans = array_pop($this->transStack);
		
		if (!$trans) { // There's no opened transaction.
			throw new ConnectionException('Can not rollback: no open transaction.');
		} else if ($trans['tid'] !== $transId) {
			$this->exec('ROLLBACK');
			$this->transStack = array();
			throw new ConnectionException('Can not rollback: transaction id is not matching inner-most transaction. Full stack rollback performed.');
		} else {
			switch ($trans['cnr']) {
				case 'full':
					$this->exec('ROLLBACK');
					$this->dirty = false; // A full rollback clears possible dirty state of inner weak transactions.
					break;
				case 'stored':
					$this->exec('ROLLBACK TO phi'.$trans['tid']);
					$this->dirty = false; // A stored rollback clears possible dirty state of inner weak transactions.
					break;
				case 'weak':
					$this->dirty = true; // Weak rollback marks state dirty and doesn't do anything on the db.
					break;
			}
		}
	}
	
	
	public function isDirty()
	{
		return $this->dirty;
	}
	

	public function markClean()
	{
		$this->dirty = false;
	}
	
	
	protected function errorFinalized($action)
	{
		throw new ConnectionException('Can not perform ' . $action . ' on a closed & finalized connection.');
	}
		
	
	protected function errorClosed($action)
	{
		throw new ConnectionException('Can not perform ' . $action . ' on a closed connection.');
	}
	
	
	protected function errorOpen($action)
	{
		throw new ConnectionException('Can not perform ' . $action . ' on an open connection.');
	}
}
?>