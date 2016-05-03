<?php
namespace Kir\TableGateway\TableGateway;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Kir\MySQL\Database;
use Serializable;
use Traversable;

class TableGatewayResultRow implements ArrayAccess, IteratorAggregate, Countable, Serializable, JsonSerializable {
	/** @var array */
	private $row;
	/** @var callable */
	private $saveCallback;
	/** @var callable */
	private $deleteCallback;

	/**
	 * @param array $row
	 * @param callable $saveCallback
	 * @param callable $deleteCallback
	 */
	public function __construct(array $row, $saveCallback, $deleteCallback) {
		$this->row = $row;
		$this->saveCallback = $saveCallback;
		$this->deleteCallback = $deleteCallback;
	}

	/**
	 * @return int
	 */
	public function save() {
		return call_user_func($this->saveCallback, $this->row);
	}

	/**
	 * @return int
	 */
	public function remove() {
		return call_user_func($this->deleteCallback, $this->row);
	}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name) {
		return $this->offsetGet($name);
	}

	/**
	 * @return Traversable
	 */
	public function getIterator() {
		return new ArrayIterator($this->row);
	}

	/**
	 * @param mixed $offset
	 * @return boolean
	 */
	public function offsetExists($offset) {
		return array_key_exists($offset, $this->row);
	}

	/**
	 * @param mixed $offset
	 * @return mixed
	 */
	public function offsetGet($offset) {
		if($this->offsetExists($offset)) {
			return $this->row[$offset];
		}
	}

	/**
	 * @param mixed $offset
	 * @param mixed $value
	 */
	public function offsetSet($offset, $value) {
		$this->row[$offset] = $value;
	}

	/**
	 * @param mixed $offset
	 */
	public function offsetUnset($offset) {
		if($this->offsetExists($offset)) {
			unset($this->row[$offset]);
		}
	}

	/**
	 * @return string
	 */
	public function serialize() {
		return serialize($this->row);
	}

	/**
	 * @param string $serialized
	 */
	public function unserialize($serialized) {
		$this->row = unserialize($serialized);
	}

	/**
	 * @return int
	 */
	public function count() {
		return count($this->row);
	}

	/**
	 * @return mixed
	 */
	function jsonSerialize() {
		return $this->row;
	}
}
