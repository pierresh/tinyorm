<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Dotenv\Dotenv;

use Pierresh\TinyOrm\ORM;
use Pierresh\TinyOrm\Repository;

use Tests\ItemGetterSetter;

final class ORMItemGetterSetterTest extends TestCase
{
	private static PDO $db;

	public static function setUpBeforeClass(): void
	{
		Dotenv::createImmutable(__DIR__ . '/..', '.env')->load();

		/** @var string $db */
		$db = $_ENV['DB_CONNECTION'];

		/** @var string $host */
		$host = $_ENV['DB_HOST'];

		/** @var string $database */
		$database = $_ENV['DB_DATABASE'];

		/** @var string $username */
		$username = $_ENV['DB_USERNAME'];

		/** @var string $password */
		$password = $_ENV['DB_PASSWORD'];

		self::$db = new PDO(
			$db . ":host=$host;dbname=$database",
			$username,
			$password
		);
	}

	public function testORMAdd(): void
	{
		$name = 'test ' . date('Y-m-d H:i:s');

		// Given I have an object with 1 field not existing in database
		$obj = new ItemGetterSetter([
			'id' => null,
			'name' => $name,
		]);

		$orm = new ORM(self::$db);

		// And I set save this object
		$result = $orm->add($obj);

		// Then the SQL should match with the expected one
		$expected =
			'INSERT INTO item_table(name, created_at) VALUES (:name, :created_at)';
		$this->assertEquals($expected, $result['sql']);

		// And the lastId and rowCount should be greated than 0
		$this->assertGreaterThan(0, $result['lastId']);

		$this->assertGreaterThan(0, $result['rowCount']);

		$obj = new ItemGetterSetter([
			'id' => $result['lastId'],
			'name' => $name,
		]);

		$this->testORMRead($orm, $obj, $name);
	}

	private function testORMRead(ORM $orm, ItemGetterSetter $obj, string $name): void
	{
		$object = $orm->find(ItemGetterSetter::class, (int) $obj->getId());

		if (!$object) {
			$this->fail('Object not found after adding it.');
		}

		$this->assertEquals($name, $object->getName());

		$this->testORMUpdate($orm, $obj);
	}

	private function testORMUpdate(ORM $orm, ItemGetterSetter $obj): void
	{
		// When I update that object
		$obj->setName('test Updated');

		// And update it
		$result = $orm->update($obj);

		$expected =
			'UPDATE item_table SET name = :name, updated_at = :updated_at WHERE id = :id';

		$this->assertEquals($expected, $result['sql']);

		// And the lastId and rowCount should be greater than 0
		$this->assertEquals(1, $result['rowCount']);

		$this->testORMRemove($orm, $obj);
	}

	private function testORMRemove(ORM $orm, ItemGetterSetter $obj): void
	{
		$result = $orm->remove($obj);

		$this->assertEquals(1, $result['rowCount']);
	}

	public function testORMReadNotFound(): void
	{
		$model = new ORM(self::$db);

		$result = $model->find(ItemGetterSetter::class, -1);

		$this->assertEquals(null, $result);
	}

	public function testORMRepository(): void
	{
		$name = 'test ' . date('Y-m-d H:i:s');

		$obj = new ItemGetterSetter([
			'id' => null,
			'name' => $name,
		]);

		$orm = new ORM(self::$db);

		$repo = $orm->getRepository(ItemGetterSetter::class);
		$result = $repo->add($obj);

		$this->assertEquals(1, $result['rowCount']);

		$this->testGetConnection($repo);
	}

	/** @param Repository<ItemGetterSetter> $repo */
	private function testGetConnection(Repository $repo): void
	{
		$connection = $repo->getConnection();

		$this->assertInstanceOf(PDO::class, $connection);

		$this->assertEquals(self::$db, $connection);
	}
}
