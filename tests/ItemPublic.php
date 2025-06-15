<?php declare(strict_types=1);

namespace Tests;

use Pierresh\TinyOrm\Mapping as ORM;

#[ORM\Table(name: 'item_table')]
class ItemPublic
{
	#[ORM\Id]
	public ?int $id;

	public string $name;
}
