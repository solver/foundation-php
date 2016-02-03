<?php
namespace Solver\Lab;

use Solver\Sql\SqlSession;
use Solver\SqlX\SqlUtils;

/**
 * Helpers for dealing with directed graph edges (links) expressed in SQL.
 * 
 * Link "names" are like object fields. For example if with an object you'd have foo.bar = baz, here you'd have:
 * 
 * - fromId = foo
 * - toId = baz
 * - name = bar
 * 
 * You can create multiple links with the same fromId and name to link a set of nodes (i.e. an unordered collection).
 * 
 * #NodeId: string; Node id, typically a foreign key to an autoincrementing positive bigint in another table.
 * #Name: string; Typically varchar or enum of a reasonable length (255 or less) for holding a name (a "field name"),
 * which identifies the relationship that the link describes, for example "children", "parent", and so on.
 * 
 * Expected SQL schema for the "edge" table:
 * 
 * - fromId: #NodeId; Node id linking to another.
 * - toId: #NodeId; Entity id being linked.
 * - name: #Name;
 * 
 * To avoid duplicate links, set all three coluhmns as a composite primary key for the table. You might want to add also
 * non-unique indexes for the following common lookups, depending on your uses cases:
 * 
 * Very common:
 * 
 * - Just column "fromId".
 * - Column "fromId" + "name".
 * 
 * Less common:
 * 
 * - Just column "toId".
 * - Column "toId" + "name".
 */
class Graph {
	protected $sess, $table;
	
	public function __construct(SqlSession $sqlSession, $edgeTable) {
		if ($sqlSession->getServerType() !== 'mysql') {
			throw new \Exception('This class has only been tested with MySQL for now.');
		}
		
		$this->sess = $sqlSession;
		$this->table = $edgeTable;
	}
	
	/**
	 * Creates a new link.
	 * 
	 * The operation is idempotent, creating a link when a link with the same parameters exists has no effect.
	 * 
	 * @param string $fromId
	 * #NodeId; Node linking to another node.
	 * 
	 * @param string $toId
	 * #NodeId; Node being linked to.
	 * 
	 * @param string $name
	 * #Name; Link name.
	 */
	public function link($fromId, $toId, $name) {
		list($sess, $table, $tableEn) = $this->getCommon();
		
		SqlUtils::insert($sess, $table, [
			'fromId' => $fromId,
			'toId' => $toId,
			'name' => $name,
		], true);
	}
	
	/**
	 * Deletes one or more links based on the given criteria.
	 * 
	 * Passing null for any criteria means you're not filtering by that field.
	 * 
	 * TODO: Prevent overly broad conditions (like passing null for all fields, thus erasing the entire graph?).
	 * 
	 * @param string $fromId
	 * #NodeId; Delete links where this node points to any another.
	 * 
	 * @param string $toId
	 * #NodeId; Delete links that point to this node.
	 * 
	 * @param null|string $name
	 * #Name; Name to filter by.
	 */
	public function unlink($fromId = null, $toId = null, $name = null) {
		list($sess, $table, $tableEn) = $this->getCommon();
		
		$where = $this->getBoolSql($fromId, $toId, $name);		
		$sess->execute("DELETE FROM $tableEn WHERE $where");
	}
	
	/**
	 * Copies the linked node(s) of one linking node to another.
	 * 
	 * The operation is idempotent, copying a link over a link with the same content has no effect.
	 * 
	 * @param string $sourceFromId
	 * #NodeId; Linking node to copy from.
	 * 
	 * @param string $targetFromId
	 * #NodeId; Linking node to copy to (this node will be pointing to the same nodes afterwards).
	 * 
	 * @param null|string $name
	 * null|#Name; If specified, copies only links with that name, otherwise, links with any name.
	 * 
	 * TODO: Support "name filter" function that can remap (rename) source names to something else (or omit entire 
	 * names from the copy operation by returning null).
	 */
	public function copyLinks($sourceFromId, $targetFromId, $name = null) {
		list($sess, $table, $tableEn) = $this->getCommon();
		
		$where = $this->getBoolSql($sourceFromId, null, $name);
		$targetFromIdEn = $sess->encodeValue($targetFromId);
		$sess->execute("REPLACE INTO $tableEn SELECT $targetFromIdEn AS fromId, toId, name FROM $tableEn WHERE $where");
	}
	
	/**
	 * Returns the unique link names this node has for all nodes it links to. 
	 *  
	 * @param string $fromId
	 * #NodeId;
	 * 
	 * @return array
	 * list<#Link>;
	 * 
	 * #Link: dict...
	 * - id: #NodeId; Node is this link refers to.
	 * - name: null|string; Link name (may be null if the link is not named).
	 */
	public function getLinks($fromId, $name = null) {
		list($sess, $table, $tableEn) = $this->getCommon();
				
		$where = $this->getBoolSql($fromId, null, $name);
		return $sess->query("SELECT toId AS id, name FROM $tableEn WHERE $where")->getAll();
	}
	
	/**
	 * Returns the unique link names this node has for all nodes it links to. 
	 *  
	 * @param string $fromId
	 * #NodeId;
	 * 
	 * @return string[]
	 */
	public function getLinkNames($fromId) {
		/** @var \Solver\Sql\SqlSession $sess */
		list($sess, $table, $tableEn) = $this->getCommon();
		
		$where = $this->getBoolSql($fromId);
		return $sess->query("SELECT DISTINCT name FROM $tableEn WHERE $where")->getAll(0);
	}
	
	protected function getCommon() {
		$sess = $this->sess;
		$table = $this->table;
		return [$sess, $table, $sess->encodeName($table)];
	}
	
	protected function getBoolSql($fromId = null, $toId = null, $name = null) {
		list($sess, $table, $tableEn) = $this->getCommon();
		
		$cond = [];
		if ($fromId !== null) $cond['fromId'] = $fromId;
		if ($toId !== null) $cond['toId'] = $toId;
		if ($name !== null) $cond['name'] = $name;
		
		if ($cond) {
			return SqlUtils::boolean($sess, $cond);
		} else {
			return '1 = 1';
		}
	}
}