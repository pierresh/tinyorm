<?php declare(strict_types=1);

namespace Pierresh\TinyOrm;

use PDO;
use PDOStatement;
use InvalidArgumentException;
use ReflectionClass;

use Pierresh\TinyOrm\Mapping\ClassMetadata;

class ORM
{
	private PDOStatement $query;
	private PDOStatement $check_fields;

	private string $table = '';
	private string $sql = '';
	private string $idField = 'id';
	private object $object;

	/** @var array<string, array{native_type:'LONG'|'FLOAT'|'VAR_STRING'|'BLOB'|'DATETIME'|'DATE'}>[] $table_fields */
	private array $table_fields = [];

	/** @var (int|float|string|null)[] $values */
	private array $values = [];

	/** @var string[] $fields */
	private array $fields = [];

	/** @var string[] $bindings */
	private array $bindings = [];

	private readonly ClassMetadata $metadata;

	/** @var array<class-string, Repository<object>> */
	private array $repositories = [];

	public function __construct(private readonly PDO $pdo)
	{
		$this->metadata = new ClassMetadata();
	}

	/**
	 * @template T of object
	 * @param class-string<T> $entityClass
	 * @return Repository<T>
	 */
	public function getRepository(string $entityClass): Repository
	{
		if (!isset($this->repositories[$entityClass])) {
			$this->repositories[$entityClass] = new class ($this, $entityClass)
				extends Repository {};
		}

		/** @var Repository<T> */
		return $this->repositories[$entityClass];
	}

	public function getConnection(): PDO
	{
		return $this->pdo;
	}

	private function defineTableFields(): void
	{
		if (
			isset($this->table_fields[$this->table]) &&
			count($this->table_fields[$this->table]) > 0
		) {
			// we already parsed that table, no need to parse again
			return;
		}

		// prettier-ignore
		$this->check_fields = $this->pdo->prepare("
			SELECT *
			FROM " . $this->table . "
			LIMIT 1
		");
		$this->check_fields->execute();

		$this->table_fields = [];

		// prettier-ignore
		foreach (range(0, $this->check_fields->columnCount() - 1) as $column_index)
		{
			/** @var false|array{native_type:'LONG'|'FLOAT'|'VAR_STRING'|'BLOB'|'DATETIME'|'DATE',name:string} $meta */
			$meta = $this->check_fields->getColumnMeta($column_index);

			if ($meta === false) {
				continue;
			}

			$this->table_fields[$this->table][$meta['name']] = $meta;
		}
	}

	/** @return array{sql:string,lastId:int,rowCount:int} */
	public function add(object $data): array
	{
		$this->cleanAttributes($data);

		$this->buildQueryInsert();

		$execute = $this->execute();

		$lastId = (int) $this->pdo->lastInsertId();

		return [
			'sql' => $this->sql,
			'lastId' => $lastId,
			'rowCount' => $execute['rowCount'],
		];
	}

	/** @return array{sql:string,rowCount:int} */
	public function update(object $data): array
	{
		$this->cleanAttributes($data);

		$this->buildQueryUpdate();

		$id = $this->idField;
		if (!isset($this->object->$id) || $this->object->$id === null) {


			$getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $this->idField)));
			if (method_exists($this->object, $getter)) {
				$this->object->$getter();
			}

			// prettier-ignore
			// throw new InvalidArgumentException($this->idField . ' has not been provided'); // @codeCoverageIgnore
		}

		$execute = $this->execute();

		return [
			'sql' => $this->sql,
			'rowCount' => $execute['rowCount'],
		];
	}

	/**
	 * @template T of object
	 * @param class-string<T> $class
	 * @return T|null
	 */
	public function find(string $class, int|string $id): ?object
	{
		$this->table = $this->metadata->getTableName($class);

		$select = implode(',', $this->metadata->getProperties($class));

		// prettier-ignore
		$this->sql = "
			SELECT $select
			FROM " . $this->table . "
			WHERE " . $this->idField . " = :id
		";
		$this->query = $this->pdo->prepare($this->sql);
		$this->query->execute(['id' => $id]);

		if ($this->query->rowCount() === 0) {
			return null;
		}

		$item = $this->query->fetch(PDO::FETCH_ASSOC);

		if (!is_array($item)) {
			return null;
		}

		$instance = new $class();
		$reflection = new ReflectionClass($instance);

		foreach ($item as $key => $value) {
			$propName = (string) $key;

			if ($reflection->hasProperty($propName)) {
				$property = $reflection->getProperty($propName);

				if (!$property->isReadOnly()) {
					$property->setAccessible(true);
					$property->setValue($instance, $value);
					continue;
				}
			}

			// fallback: try setter method
			// prettier-ignore
			$setter = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $propName)));
			if (method_exists($instance, $setter)) {
				$instance->$setter($value);
			}
		}

		return $instance;
	}

	/**
	 * @return array{sql:string,rowCount:int}
	 */
	public function remove(object $data): array
	{
		$this->cleanAttributes($data);

		$this->buildQueryDelete();

		$this->query = $this->pdo->prepare($this->sql);

		$idValue = $this->getIdValue();

		$this->query->execute(['id' => $idValue]);

		return [
			'sql' => $this->sql,
			'rowCount' => $this->query->rowCount(),
		];
	}

	private function tableHasField(string $field): bool
	{
		return isset($this->table_fields[$this->table][$field]);
	}

	private function buildQueryDelete(): void
	{
		// prettier-ignore
		$this->sql = "
			DELETE
			FROM " . $this->table . "
			WHERE " . $this->idField . " = :id
		";
	}

	private function buildQueryInsert(): void
	{
		// Auto-fill automatically `created_at` if the field exists in the table
		if ($this->tableHasField('created_at')) {
			$this->fields[] = 'created_at';
			$this->bindings[] = ':created_at';
			$this->values[] = date('Y-m-d H:i:s');
		}

		$extra_i = '';
		$extra_v = '';

		// prettier-ignore
		$this->sql = 'INSERT INTO ' . $this->table . '(' . $extra_i . implode(', ', $this->fields) . ')';
		// prettier-ignore
		$this->sql .= ' VALUES (' . $extra_v . implode(', ', $this->bindings) . ')';
	}

	private function buildQueryUpdate(): void
	{
		// Auto-fill automatically `updated_at` if the field exists in the table
		$update = '';
		if ($this->tableHasField('updated_at')) {
			$this->fields[] = 'updated_at';
			$this->bindings[] = ':updated_at';
			$this->values[] = date('Y-m-d H:i:s');
		}

		foreach ($this->fields as $key) {
			if ($update === '') {
				$update .= $key . ' = :' . $key;
			} else {
				$update .= ', ' . $key . ' = :' . $key;
			}
		}

		$this->sql = 'UPDATE ' . $this->table . ' SET ';

		// prettier-ignore
		$this->sql .= $update . ' WHERE ' . $this->idField . ' = :' . $this->idField;
	}

	private function cleanAttributes(object $object): void
	{
		$this->object = $object;

		$this->table = $this->metadata->getTableName($object);

		$this->idField = $this->metadata->getIdField($object);

		$this->defineTableFields();

		$this->fields = [];
		$this->bindings = [];
		$this->values = [];

		$this->setAttributes();
	}

	private function setAttributes(): void
	{
		// Skip fields that are populated automatically
		$to_skip = ['created_at', 'updated_at'];

		$reflection = new ReflectionClass($this->object);

		foreach ($reflection->getProperties() as $property) {
			$property->setAccessible(true);

			$key = $property->getName();

			if (in_array($key, $to_skip, true)) {
				continue;
			}

			$index = $this->tableHasField($key);

			if ($index === false || $key === $this->idField) {
				continue;
			}

			$this->fields[] = $key;
			$this->bindings[] = ':' . $key;

			/** @var float|int|string|null $value */
			$value = $property->getValue($this->object);

			$this->values[] = $value;
		}
	}

	private function bindValues(): void
	{
		$this->query = $this->pdo->prepare($this->sql);
		$counter = count($this->fields);

		for ($i = 0; $i < $counter; $i++) {
			$field = $this->table_fields[$this->table][$this->fields[$i]];

			if (
				$field['native_type'] === 'LONG' &&
				$this->values[$i] !== null
			) {
				$this->query->bindValue(
					$this->bindings[$i],
					$this->values[$i],
					PDO::PARAM_INT
				);
			} else {
				$this->query->bindValue($this->bindings[$i], $this->values[$i]);
			}
		}

		$idValue = $this->getIdValue();

		if ($idValue !== null) {
			$this->query->bindValue($this->idField, $idValue);
		}
	}

	private function getIdValue(): mixed {
		$id = $this->idField;

		if (isset($this->object->$id) && $this->object->$id !== null) {
			return $this->object->$id;
		}

		$getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $this->idField)));
		if (method_exists($this->object, $getter) && $this->object->$getter() !== null) {
			return $this->object->$getter();
		}

		return null;
	}

	/** @return array{sql:string,rowCount:int} */
	private function execute(): array
	{
		$this->bindValues();

		// prettier-ignore
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

			if ($this->query->execute() === false) {

			dd($this->sql);
		}

		$rowCount = $this->query->rowCount();

		return [
			'sql' => $this->sql,
			'rowCount' => $rowCount,
		];
	}
}
