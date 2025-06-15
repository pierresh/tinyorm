<?php declare(strict_types=1);

namespace Pierresh\TinyOrm\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Table
{
	public function __construct(public readonly string|null $name = null) {}
}
