<?php declare(strict_types=1);

namespace Pierresh\TinyOrm\Mapping;

use InvalidArgumentException;
use ReflectionClass;

use Pierresh\TinyOrm\Exception\MappingException;

final class ClassMetadata
{
	/** @var array<class-string, string> */
	private array $tableCache = [];

	/** @var array<class-string, string> */
	private array $idFieldCache = [];

	/** @var array<class-string, string[]> */
	private array $propertiesCache = [];

	/**
	 * @param class-string|object $entity
	 */
	public function getTableName($entity): string
	{
		$class = is_object($entity) ? $entity::class : $entity;

		if (isset($this->tableCache[$class])) {
			return $this->tableCache[$class];
		}

		$reflection = new ReflectionClass($class);

		$attributes = $reflection->getAttributes(Table::class);

		if (count($attributes) === 0) {
			// prettier-ignore
			throw new MappingException("The class ' . $class . ' does not have a table attribute defined.");
		}

		/** @var Table $tableAttr */
		$tableAttr = $attributes[0]->newInstance();
		$name = $this->sanitizeVariable((string) $tableAttr->name);

		$this->tableCache[$class] = $name;

		return $name;
	}

	public function getIdField(object $entity): string
	{
		$class = $entity::class;

		if (isset($this->idFieldCache[$class])) {
			return $this->idFieldCache[$class];
		}

		$reflection = new ReflectionClass($entity);

		foreach ($reflection->getProperties() as $property) {
			if (count($property->getAttributes(Id::class)) > 0) {
				$this->idFieldCache[$class] = $property->getName();
				return $property->getName();
			}
		}

		throw new MappingException(
			"The class ' . $class . ' does not have an id attribute defined."
		);
	}

	/**
	 * @param class-string $class
	 * @return string[]
	 */
	public function getProperties(string $class): array
	{
		if (isset($this->propertiesCache[$class])) {
			return $this->propertiesCache[$class];
		}

		$reflection = new ReflectionClass($class);

		$properties = $reflection->getProperties();

		$fields = [];

		foreach ($properties as $property) {
			$fields[] = $property->getName();
		}

		$this->propertiesCache[$class] = $fields;

		return $this->propertiesCache[$class];
	}

	private function sanitizeVariable(string $name): string
	{
		$sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $name);

		if ($sanitized === null) {
			throw new InvalidArgumentException('Invalid name: ' . $name); // @codeCoverageIgnore
		}

		return $sanitized;
	}
}
