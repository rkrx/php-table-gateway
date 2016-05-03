<?php
namespace Kir\TableGateway;

use Exception;
use Kir\MySQL\Builder\DBExpr;
use Kir\MySQL\Database;
use Kir\TableGateway\TableGateway\SelectResult;
use Kir\TableGateway\TableGateway\TableGatewayResultRow;

/**
 * @internal
 */
class TableGateway {
	/** @var Database */
	private $db;
	/** @var array */
	private $primaryKeys;
	/** @var array */
	private $options;
	/** @var string */
	private $tableName;

	/**
	 * @param Database $db
	 * @param string $tableName
	 * @param array $primaryKeys
	 * @param array $options
	 */
	public function __construct(Database $db, $tableName, array $primaryKeys, array $options = []) {
		$this->db = $db;
		$this->primaryKeys = array_values($primaryKeys);
		$options = array_merge(['allow-delete-all' => false], $options);
		$options = array_merge(['calc-found-rows' => false], $options);
		$options = array_merge(['preserve-types' => true], $options);
		$options = array_merge(['forced-predefined-field-values' => []], $options);
		$this->options = $options;
		$this->tableName = (string) $tableName;
	}

	/**
	 * @param array $data
	 * @param array $options
	 * @return SelectResult
	 */
	public function select(array $data = [], array $options = []) {
		$select = $this->db->select()
		->from('t', $this->tableName);
		if(array_key_exists('select-fields', $options) && is_array($options['select-fields'])) {
			$select->fields($options['select-fields']);
		}
		if(array_key_exists('calc-found-rows', $options)) {
			$select->setCalcFoundRows($options['calc-found-rows']);
		}
		if(array_key_exists('preserve-types', $options)) {
			$select->setPreserveTypes($options['preserve-types']);
		}
		if(array_key_exists('select-distinct', $options)) {
			$select->distinct($options['select-distinct']);
		}
		foreach($data as $fieldName => $value) {
			if($value instanceof DBExpr) {
				$select->where($value);
			} else {
				$select->where(sprintf("%s=?", $this->db->quoteField($fieldName)), $value);
			}
		}
		foreach($this->options['forced-predefined-field-values'] as $fieldName => $value) {
			$select->where(sprintf("%s=?", $this->db->quoteField($fieldName)), $value);
		}
		if(array_key_exists('debug', $options)) {
			$select->debug($options['debug']);
		}
		$rowFactoryCallback = function (array $row) {
			return new TableGatewayResultRow($row, function (array $row) {
				return $this->update($row);
			}, function (array $row) {
				return $this->delete($row);
			});
		};
		return new SelectResult($select, $rowFactoryCallback, $options);
	}

	/**
	 * @param array $primaryKey
	 * @param array $options
	 * @return TableGatewayResultRow
	 * @throws Exception
	 */
	public function get(array $primaryKey, array $options = []) {
		$select = $this->select($primaryKey, $options);
		$select->query()->limit(1);
		$rows = $this->select()->getIterator();
		foreach($rows as $row) {
			return $row;
		}
		throw new Exception('Entity not found');
	}

	/**
	 * @param array $data
	 * @param array $options
	 * @return int
	 */
	public function insert(array $data, array $options = []) {
		$options = array_merge($this->options, $options);
		$insert = $this->db->insert()
		->into($this->tableName);
		if(array_key_exists('mask', $options) && is_array($options['mask'])) {
			$insert->setMask(array_values($options['mask']));
		}
		if(count($this->primaryKeys) === 1) {
			$insert->setKey($this->primaryKeys[0]);
		}
		if(array_key_exists('defaults', $options)) {
			$data = array_merge($options['defaults'], $data);
		}
		if(array_key_exists('insert-defaults', $options)) {
			$data = array_merge($options['insert-defaults'], $data);
		}
		if(array_key_exists('insert-ignore', $options)) {
			$insert->setIgnore($options['insert-ignore']);
		}
		$data = array_intersect_key($data, array_combine($this->primaryKeys, $this->primaryKeys));
		$insert->addAll($data);
		if(array_key_exists('debug', $options)) {
			$insert->debug($options['debug']);
		}
		return $insert->debug()->run();
	}

	/**
	 * @param array $data
	 * @param array $options
	 * @return int
	 */
	public function upsert(array $data, array $options = []) {
		$options = array_merge($this->options, $options);
		$insert = $this->db->insert()
		->into($this->tableName);
		if(array_key_exists('mask', $options) && is_array($options['mask'])) {
			$insert->setMask(array_values($options['mask']));
		}
		if(count($this->primaryKeys) === 1) {
			$insert->setKey($this->primaryKeys[0]);
		}
		if(array_key_exists('defaults', $options)) {
			$data = array_merge($options['defaults'], $data);
		}
		if(array_key_exists('insert-defaults', $options)) {
			$data = array_merge($options['insert-defaults'], $data);
		}
		$valueData = array_diff_key($data, array_combine($this->primaryKeys, $this->primaryKeys));
		$insert->addOrUpdateAll($valueData);
		$keyData = array_intersect_key($data, array_combine($this->primaryKeys, $this->primaryKeys));
		$keyData = array_merge($keyData, $this->options['forced-predefined-field-values']);
		$insert->addAll($keyData);
		if(array_key_exists('debug', $options)) {
			$insert->debug($options['debug']);
		}
		return $insert->debug()->run();
	}

	/**
	 * @param array $data
	 * @param array $options
	 * @return int
	 */
	public function update(array $data, array $options = []) {
		$options = array_merge($this->options, $options);
		$update = $this->db->update()
		->table($this->tableName);
		if(array_key_exists('mask', $options) && is_array($options['mask'])) {
			$update->setMask(array_values($options['mask']));
		}
		if(array_key_exists('defaults', $options)) {
			$data = array_merge($options['defaults'], $data);
		}
		if(array_key_exists('update-defaults', $options)) {
			$data = array_merge($options['update-defaults'], $data);
		}
		$keyData = array_intersect_key($data, array_combine($this->primaryKeys, $this->primaryKeys));
		foreach($keyData as $fieldName => $value) {
			$update->where(sprintf("%s=?", $this->db->quoteField($fieldName)), $value);
		}
		foreach($this->options['forced-predefined-field-values'] as $fieldName => $value) {
			$update->where(sprintf("%s=?", $this->db->quoteField($fieldName)), $value);
		}
		$valueData = array_diff_key($data, array_combine($this->primaryKeys, $this->primaryKeys));
		$update->setAll($valueData);
		if(array_key_exists('debug', $options)) {
			$update->debug($options['debug']);
		}
		return $update->debug()->run();
	}

	/**
	 * @param array $data
	 * @param array $options
	 * @return int
	 * @throws Exception
	 */
	public function delete(array $data = null, array $options = []) {
		if(!is_array($data)) {
			throw new Exception('Missing data parameter');
		}
		$options = array_merge($this->options, $options);
		$delete = $this->db->delete()
		->from($this->tableName);
		if(array_key_exists('delete-defaults', $options)) {
			$data = array_merge($options['delete-defaults'], $data);
		}
		$keyData = array_intersect_key($data, array_combine($this->primaryKeys, $this->primaryKeys));
		if(count($keyData) !== count($this->primaryKeys) && !$options['allow-delete-all']) {
			throw new Exception("You try to delete all data from {$this->tableName} which is not allowed by configuration");
		}
		foreach($keyData as $fieldName => $value) {
			$delete->where(sprintf("%s=?", $this->db->quoteField($fieldName)), $value);
		}
		foreach($this->options['forced-predefined-field-values'] as $fieldName => $value) {
			$delete->where(sprintf("%s=?", $this->db->quoteField($fieldName)), $value);
		}
		if(array_key_exists('debug', $options)) {
			$delete->debug($options['debug']);
		}
		return $delete->debug()->run();
	}
}
