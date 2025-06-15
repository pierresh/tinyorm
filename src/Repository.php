<?php declare(strict_types=1);

namespace Pierresh\TinyOrm;

use PDO;

use Pierresh\TinyOrm\ORM;

/**
 * @template T of object
 */
abstract class Repository
{
	protected ORM $orm;

	/** @var class-string<T> */
	protected string $entityClass;

	/**
	 * @param ORM $orm
	 * @param class-string<T> $entityClass
	 */
	public function __construct(ORM $orm, string $entityClass)
	{
		$this->orm = $orm;
		$this->entityClass = $entityClass;
	}

	public function getConnection(): PDO
	{
		return $this->orm->getConnection();
	}

	/**
	 * @param T $entity
	 * @return array{sql: string, lastId: int, rowCount: int}
	 */
	public function add(object $entity): array
	{
		return $this->orm->add($entity);
	}

	/**
	 * @param T $entity
	 * @return array{sql: string, rowCount: int}
	 */
	public function update(object $entity): array
	{
		return $this->orm->update($entity);
	}

	/**
	 * @param int|string $id
	 * @return T|null
	 */
	public function find(int|string $id): ?object
	{
		return $this->orm->find($this->entityClass, $id);
	}

	/**
	 * @param T $entity
	 * @return array{sql: string, rowCount: int}
	 */
	public function delete(object $entity): array
	{
		return $this->orm->remove($entity);
	}
}
