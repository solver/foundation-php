<?php
namespace Solver\Base;

// TODO: Document (see trait Magic).
interface MagicOptions {
	const EXPANDO = 1;
	const CALL_PROPERTIES = 2;
	const GET_METHODS = 4;
	const GET_METHODS_CACHE = 8;
}