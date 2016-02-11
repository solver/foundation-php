<?php
namespace Solver\Lab;

use Solver\Sql\SqlSession;
use Solver\SqlX\SqlUtils;
use Optilocal\SaTool\Utils;

/**
 * TODO: Merge this into Sidekick? then we can also have constructors, destructors, and trait-aware updates, gets etc.
 * 
 * Helpers for dealing with directed graphs expressed as two SQL tables: node and link.
 * 
 * You're expected to use additional tables for storing node state, and have their primary key be a foreign key
 * pointing node.id (1:1 relationship).
 * 
 * #NodeId: string; Node id, typically a foreign key to an autoincrementing positive bigint in another table.
 * 
 * #LinkName: string; Typically varchar or enum of a reasonable length (255 or less) for holding a name (a
 * "field name"), which identifies the relationship that the link describes, for example "children", "parent", and so
 * on.
 * 
 * Expected SQL schema for the "node" table:
 * 
 * - id: #NodeId; Node id. Typically an autoincrementing primary key.
 * 
 * Expected SQL schema for the "link" table:
 * 
 * - fromId: #NodeId; Node id linking to another.
 * - toId: #NodeId; Entity id being linked.
 * - name: #LinkName;
 * 
 * To avoid duplicate links, set all three columns as a composite primary key for the table. You might want to add also
 * non-unique indexes for the following common lookups, depending on your use cases:
 * 
 * - Very common: just column "fromId"; column "fromId" + "name".
 * - Less common: just column "toId"; column "toId" + "name".
 * 
 * Link "names" are like object fields. For example if with an object you'd have foo.bar = baz, here you'd have:
 * 
 * - fromId = foo
 * - toId = baz
 * - name = bar
 * 
 * You can create multiple links with the same fromId and name to link a set of nodes (i.e. an unordered collection).
 */
class Graph {
	protected $sess, $nodeTable, $linkTable;
	
	public function __construct(SqlSession $sqlSession, $nodeTable, $linkTable) {
		if ($sqlSession->getServerType() !== 'mysql') {
			throw new \Exception('This class has only been tested with MySQL for now.');
		}
		
		$this->sess = $sqlSession;
		$this->nodeTable = $nodeTable;
		$this->linkTable = $linkTable;
	}
	
	/**
	 * Creates a new node, returns its id.
	 *  
	 * @param null|string $id
	 * null|#NodeId; You shouldn't specify an id here if you're using an autoincrementing primary key, but you can 
	 * supply one if you don't.
	 * 
	 * @return string
	 * #NodeId;
	 */
	public function createNode($id = null) {
		list($sess, $nodeTable, $nodeTableEn, $linkTable, $linkTableEn) = $this->getCommon();
		
		$row = [];
		if ($id !== null) $row['id'] = $id;
		
		SqlUtils::insert($sess, $nodeTable, $row, true);
		
		if ($id === null) $id = $sess->getLastIdentity();
		
		return $id;
	}
	
	/**
	 * Deletes a node. If a node with this id doesn't exist, returns silently with no errors.
	 * 
	 * And all links pointing from, or to the deletes node will be deleted. Alternatively, if you supply a
	 * $replacementId, the links where the deleted node is "fromId" will be deleted, and the links where the deleted 
	 * node is "toId" will be preserved, and "toId" will be replaced with the given $replacementId (it can be a node
	 * you have designated as a null node, for example).
	 * 
	 * @param string $id
	 * #NodeId;
	 * 
	 * @param null|string $replacementId
	 * null|#NodeId;
	 * 
	 * @return void
	 */
	public function deleteNode($id, $replacementId = null) {
		$this->deleteNodeList([$id], $replacementId);
	}
	
	/**
	 * Identical semantics as deleteNode(), but it allows deleting multiple ids at once.
	 * 
	 * @param array $idList
	 * list<#NodeId>;
	 * 
	 * @param string $replacementId
	 * #NodeId;
	 * 
	 * @return void
	 */
	public function deleteNodeList(array $idList, $replacementId = null) {
		list($sess, $nodeTable, $nodeTableEn, $linkTable, $linkTableEn) = $this->getCommon();
		
		$tid = $sess->begin(null, $sess::TF_VIRTUAL);
		
		$sql = SqlUtils::encodeInto($sess, "DELETE FROM $linkTableEn WHERE id = ?",	 []);
		
		// TODO: We can obviously optimize this. Later.
		foreach ($idList as $id) $this->unlink($id);
			
		if ($replacementId === null) {
			// TODO: We can obviously optimize this. Later.
			foreach ($idList as $id) $this->unlink(null, $id);
		} else {
			$idListEn = Utils::inExpr($sess, 'toId', $idList);
			$sql = SqlUtils::encodeInto($sess, "UPDATE $linkTableEn SET toId = ? WHERE $idListEn", [$replacementId]);
		}
		
		$sess->commit($tid);
	}
	
	/**
	 * Creates a new link from one node to another (directed line, arrow).
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
	 * #LinkName; Link name.
	 * 
	 * @param bool $replacePrevious
	 * Default false. If true, any previous link(s) from the same node, with the same id, will be removed before this
	 * one is added.
	 * 
	 * @return void
	 */
	public function link($fromId, $toId, $name, $replacePrevious = false) {
		list($sess, $nodeTable, $nodeTableEn, $linkTable, $linkTableEn) = $this->getCommon();
		
		$sess->transactional(null, $sess::TF_VIRTUAL, function () use ($fromId, $toId, $name, $replacePrevious, $sess, $linkTable, $linkTableEn) {
			if ($replacePrevious) {
				$sql = SqlUtils::encodeInto($sess, "DELETE FROM $linkTableEn WHERE fromId = ? AND name = ?", [$fromId, $name]);
				$sess->execute($sql);
			}
			
			SqlUtils::insert($sess, $linkTable, [
				'fromId' => $fromId,
				'toId' => $toId,
				'name' => $name,
			], true);
		});		
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
	 * #LinkName; Name to filter by.
	 */
	public function unlink($fromId = null, $toId = null, $name = null) {
		list($sess, $nodeTable, $nodeTableEn, $linkTable, $linkTableEn) = $this->getCommon();
		
		$where = $this->getBoolSql($fromId, $toId, $name);		
		$sess->execute("DELETE FROM $linkTableEn WHERE $where");
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
	 * null|#LinkName; If specified, copies only links with that name, otherwise, links with any name.
	 * 
	 * TODO: Support "name filter" function that can remap (rename) source names to something else (or omit entire 
	 * names from the copy operation by returning null).
	 * 
	 * TODO: Support "replacePrevious" style flag for this method, like link(). We might need to have a map of names
	 * setting this property to true and false, for each, respectively (this should be optional as it'll slow down 
	 * execution).
	 */
	public function copyLinks($sourceFromId, $targetFromId, $name = null) {
		list($sess, $nodeTable, $nodeTableEn, $linkTable, $linkTableEn) = $this->getCommon();
		
		$where = $this->getBoolSql($sourceFromId, null, $name);
		$targetFromIdEn = $sess->encodeValue($targetFromId);
		$sess->execute("REPLACE INTO $linkTableEn SELECT $targetFromIdEn AS fromId, toId, name FROM $linkTableEn WHERE $where");
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
	 * - id: #NodeId; Node this link refers to.
	 * - name: null|string; Link name (may be null if the link is not named).
	 */
	public function getLinks($fromId, $name = null) {
		list($sess, $nodeTable, $nodeTableEn, $linkTable, $linkTableEn) = $this->getCommon();
				
		$where = $this->getBoolSql($fromId, null, $name);
		return $sess->query("SELECT toId AS id, name FROM $linkTableEn WHERE $where")->getAll();
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
		list($sess, $nodeTable, $nodeTableEn, $linkTable, $linkTableEn) = $this->getCommon();
		
		$where = $this->getBoolSql($fromId);
		return $sess->query("SELECT DISTINCT name FROM $linkTableEn WHERE $where")->getAll(0);
	}
	
	protected function getCommon() {
		$sess = $this->sess;
		$nodeTable = $this->nodeTable;
		$linkTable = $this->linkTable;
		return [$sess, $nodeTable, $sess->encodeName($nodeTable), $linkTable, $sess->encodeName($linkTable)];
	}
	
	protected function getBoolSql($fromId = null, $toId = null, $name = null) {
		list($sess, $linkTable, $linkTableEn) = $this->getCommon();
		
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