<?php
namespace Solver\Web;

use Solver\Logging\StatusLog;
use Solver\Accord\ToValue;
use Solver\Accord\FromValue;
use Solver\Toolbox\CollectionUtils;

/**
 * IMPORTANT: This is an internal component of Solver\Web. Do not extend. It may change or go away without warning.
 * 
 * TODO: Document methods.
 */
abstract class DataBox implements ToValue, FromValue {
	protected $fields;
	protected $path = null;
	
	public function __construct($fields = null) {
		$this->fields = $fields;
	}
	
	/**
	 * A shortcut to the getField() and setField() methods, depending on whether you pass one or two arguments, resp.
	 * 
	 * Usage:
	 * 
	 * <code>
	 * // This...
	 * $container('foo.bar.baz');
	 * // ...is the same as this:
	 * $container->getField('foo.bar.baz')
	 * 
	 * // This...
	 * $container('foo.bar.baz', $value);
	 * // ...is the same as this:
	 * $container->setField('foo.bar.baz', $value)
	 * </code>
	 * 
	 * $model('
	 * @param unknown $path
	 * @param unknown $value
	 */
	public function __invoke($path, $value = null) {
		// We want to differentiate passing no second argument from passing null.
		if (func_num_args() == 2) {
			$this->setField($path, $value);
		} else {
			return $this->getField($path);
		}
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Accord\FromValue::fromValue()
	 * 
	 * @param array $value
	 * dict...; Dictionary of parameters.
	 * 
	 * @return $this
	 */	
	public static function fromValue($value, StatusLog $log = null) {
		// FIXME: Doesn't respect $this->path;
		new static($value);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Accord\ToValue::toValue()
	 * 
	 * @return array
	 * dict...; Dictionary of parameters
	 */
	public function toValue() {
		// FIXME: Doesn't respect $this->path;
		return $this->fields;
	}	
	
	/**
	 * {@inheritDoc}
	 * @see JsonSerializable::jsonSerialize()
	 */
	public function jsonSerialize() {
		return $this->toValue();
	}

	public function at($path) {
		$sub = new static();
		$sub->fields = & $this->fields; // The child keeps a live link to the parent's data.
		$sub->path = $this->realPath($path);
		return $sub;
	}
	
	public function getField($path) {
		$path = $this->realPath($path);
		
		if ($path) {
			$parent = CollectionUtils::drill($this->fields, $path, $keyOut);
			return isset($parent[$keyOut]) ? $parent[$keyOut] : null;
		} else {
			return $this->fields;
		}
	}
	
	public function setField($path, $value) {
		$path = $this->realPath($path);
		
		if ($path) {
			$parent = & CollectionUtils::drill($this->fields, $path, $keyOut, true, true);
			$parent[$keyOut] = $value;
		} else {
			$this->fields = $value;
		}
	}
	
	public function pushField($path, $value) {
		$path = $this->realPath($path);
		$path[] = 0; // Unused last segment, we need a valid parent to push to.
		
		$parent = & CollectionUtils::drill($this->fields, $path, $keyOut, true, true);
		$parent[] = $value;
	}
	
	protected function realPath($path) {
		if ($path === null || $path === '') {
			$path = [];
		} else {
			$path = is_array($path) ? $path : explode('.', $path);
		}
		
		if ($this->path === null) return $path;
		else return array_merge($this->path, $path);
	}
}