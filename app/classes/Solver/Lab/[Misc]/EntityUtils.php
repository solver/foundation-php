<?php
namespace Solver\Shake;

/**
 * Common operations when working with SQL-stored entities in services/models.
 * 
 * @author Stan Vass
 * @copyright © 2012-2013 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class EntityUtils {	
	/**
	 * Allows dynamic (no schema) fields for entities stored in SQL, by encoding the dynamic fields as JSON in table
	 * column "dynamic".
	 * 
	 * @param array $entity
	 * 
	 * @param array $staticFields
	 * Optional (default = null). A list of fields in the entity which map to actual SQL table columns (anything else
	 * is considered dynamic).
	 * 
	 * @param array $containerStaticFields
	 * Optional (default = null). A list of static fields in the entity whose value is a container (list, dict), and
	 * need to be encoded as JSON (you don't need to specify this for dynamic fields as they're always encoded).
	 *  
	 * @return array
	 */
	public static function encodeDynamicEntity(array $entity, $staticFields = null, $containerStaticFields = null) {		
		if ($containerStaticFields) foreach ($containerStaticFields as $field) if (\key_exists($field, $entity)) {
			$entity[$field] = \json_encode($entity[$field], \JSON_UNESCAPED_UNICODE);
		}		
		
		$static = [];
		
		if ($staticFields) foreach ($staticFields as $field) if (\key_exists($field, $entity)) {
			$static[$field] = $entity[$field];
			unset($entity[$field]);
		}
		
		$static['dynamic'] = \json_encode($entity, \JSON_UNESCAPED_UNICODE);
		
		return $static;
	}
	
	/**
	 * Decodes a dynamic entity into a flat dict.
	 * 
	 * @param array $entity
	 * 
	 * @param array $containerStaticFields
	 * Optional (default = null). A list of static fields in the entity whose value is a container (list, dict), and
	 * that value has to be decoded from JSON in the database. You don't need to specify this for dynamic fields, they
	 * are always decoded from JSON.
	 * 
	 * @return array
	 */
	public static function decodeDynamicEntity(array $entity, $containerStaticFields = null) {
		if ($containerStaticFields) foreach ($containerStaticFields as $field) if (\key_exists($field, $entity)) {
			$entity[$field] = \json_decode($entity[$field], true);
		}	
		
		if (isset($entity['dynamic'])) {
			$dynamic = \json_decode($entity['dynamic'], true);
			unset($entity['dynamic']);
			return $entity + $dynamic;
		} else {
			return $entity;	
		}
	}
}