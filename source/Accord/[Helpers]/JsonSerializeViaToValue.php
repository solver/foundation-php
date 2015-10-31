<?php
namespace Solver\Accord;

/**
 * Default implementation of jsonSerialize() for interface ToValue.
 */
trait JsonSerializeViaToValue {
	abstract public function toValue();
	
	public function jsonSerialize() {
		return $this->toValue();
	}
}