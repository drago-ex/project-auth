<?php declare(strict_types=1);

use App\Model\User\UserEntity;
use Dibi\Connection;
use Dibi\Result;
use Drago\Database\Entity;
use Drago\Database\ExtraFluent;
use Drago\Database\Table;


/**
 * The table of the users in memory, for the test doubles of UserRepository: get() returns the record
 * of the id, save() keeps a copy of the saved user.
 * @extends Table<UserEntity>
 */
readonly class MemoryTable extends Table
{
	public function __construct(
		/** @var ArrayObject<int, UserEntity> */
		public ArrayObject $records = new ArrayObject,
		/** @var ArrayObject<int, UserEntity> */
		public ArrayObject $saved = new ArrayObject,
	) {
		parent::__construct(
			new Connection(['driver' => 'mysqli', 'lazy' => true]),
			UserEntity::Table,
			UserEntity::ColumnId,
			UserEntity::class,
		);
	}


	/** @return ExtraFluent<UserEntity> */
	public function get(int $id): ExtraFluent
	{
		return new MemoryFluent($this->getConnection(), $this->records[$id] ?? null);
	}


	public function save(Entity|iterable $args): Result|int|null
	{
		assert($args instanceof UserEntity);
		$this->saved[] = clone $args;
		return 1;
	}
}


/**
 * A query whose record is given, without the database.
 * @extends ExtraFluent<UserEntity>
 */
class MemoryFluent extends ExtraFluent
{
	public function __construct(
		Connection $connection,
		private readonly ?UserEntity $user,
	) {
		parent::__construct($connection);
	}


	public function record(): ?UserEntity
	{
		return $this->user;
	}
}
