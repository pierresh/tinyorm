<?php declare(strict_types=1);

namespace Pierresh\TinyOrm\Exception;

use Exception;

class MappingException extends Exception
{
	public function __construct(string $message)
	{
		parent::__construct($message);
	}
}
