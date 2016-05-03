<?php
namespace Kir\TableGateway\TableGateway;

use IteratorAggregate;
use Kir\MySQL\Builder\RunnableSelect;
use Traversable;

class SelectResult implements IteratorAggregate {
	/** @var RunnableSelect */
	private $select;
	/** @var array */
	private $options;
	/** @var callable */
	private $rowFactoryCallback;

	/**
	 * @param RunnableSelect $select
	 * @param callable $rowFactoryCallback
	 * @param array $options
	 */
	public function __construct(RunnableSelect $select, $rowFactoryCallback, array $options = []) {
		$this->select = $select;
		$this->options = $options;
		$this->rowFactoryCallback = $rowFactoryCallback;
	}

	/**
	 * @return RunnableSelect
	 */
	public function query() {
		return $this->select;
	}

	/**
	 * @return int
	 */
	public function getFoundRows() {
		return $this->getFoundRows();
	}

	/**
	 * @return TableGatewayResultRow[]|Traversable|array[]
	 */
	public function getIterator() {
		return $this->select->fetchRowsLazy(function (array $row) {
			return call_user_func($this->rowFactoryCallback, $row);
		});
	}
}
