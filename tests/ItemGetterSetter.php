<?php declare(strict_types=1);

namespace Tests;

use Pierresh\TinyOrm\Mapping as ORM;

#[ORM\Table(name: 'item_table')]
class ItemGetterSetter
{
	#[ORM\Id]
	private ?int $id;

	private string $name;

	/** @param array{id?: ?int, name?: string} $data */
	public function __construct(array $data = [])
	{
		$this->id = $data['id'] ?? null;
		$this->name = $data['name'] ?? '';
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function setId(int $id): void
	{
		$this->id = $id;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): void
	{
		$this->name = $name;
	}
}
