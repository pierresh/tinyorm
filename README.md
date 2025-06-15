[![code style: prettier](https://img.shields.io/badge/code_style-prettier-ff69b4.svg?style=flat-square)](https://github.com/prettier/prettier)
[![PHPStan Enabled](https://img.shields.io/badge/PHPStan-enabled-brightgreen.svg?style=flat)](https://github.com/phpstan/phpstan)

# TinyOrm

**A lightweight and dependency-free ORM for PHP**

TinyOrm is a simple Object-Relational Mapper (ORM) that works directly with existing **PDO** connections. It is ideal for **legacy systems**, **simple projects**, or **multi-tenant architectures** where dynamic PDO connections are required.

## Rationale

Most major ORMs (like Doctrine or Eloquent) do not support existing PDO connections out-of-the-box, and often introduce unnecessary complexity.

This ORM focus on CRUD operations. For Browse operations, it is better to use a dedicated query builder like Medoo.

## Example of use:

```php
$foo = new Item(...);

$orm = new ORM($pdo);

// To add an item in database
$orm->add($foo);

// To update an item in database
$orm->update($foo);

// To delete an item from database
$orm->remove($foo);

// To read an item from database
$bar = $orm->find(Item::class, $foo->id);
```
It can also be used as a repository:
```php
$repo = (new ORM($pdo))->getRepository(Item::class);

// Then the ORM can be used from the repository
$result = $repo->add($foo);

// And the PDO connection can be retrieved from the repository
$pdo = $repo->getConnection();
```

## Attributes

2 attributes are necessary for this ORM in entities:

- `#[ORM\Table(name: 'your_table')]` — Defines the table name
- `#[ORM\Id]` — Marks the primary key field

You can define entities with public properties:
```php
<?php declare(strict_types=1);

namespace Tests;

use Pierresh\TinyOrm\Mapping as ORM;

#[ORM\Table(name: 'item_table')]
class Item
{
	#[ORM\Id]
	public ?int $id;

	public string $name;
}
```
Or with getters/setters:
```php
<?php declare(strict_types=1);

namespace Tests;

use Pierresh\TinyOrm\Mapping as ORM;

#[ORM\Table(name: 'item_table')]
class Item
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
```

## Development

Clone the repository and install the dependencies:

```bash
git clone https://github.com/pierresh/tinyorm

cd tinyorm

composer install

npm install
```

Create the table in database and fill the `.env` file
```sql
# For MySql
CREATE TABLE `item_table` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

✅ Run unit tests using **PEST** with watcher

```bash
composer test
```

🧹 Reformat using **Prettier**

```bash
composer format
```

✨ Run refactors using **Rector**

```bash
composer refactor
```

⚗️ Run static analysis using **PHPStan**:

```bash
composer stan
```

🚀 Run the entire quality suite:

```bash
composer quality
```
