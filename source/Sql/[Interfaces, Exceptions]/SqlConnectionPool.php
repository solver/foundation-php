<?php
namespace Solver\Sql;

/**
 * TODO: Extract standard interface from the implementation.
 */
interface SqlPool {
	public function getSession();
	public function closeSessions();
}