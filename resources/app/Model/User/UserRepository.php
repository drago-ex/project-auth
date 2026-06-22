<?php

declare(strict_types=1);

namespace App\Model\User;

use Dibi\Connection;
use Dibi\Exception;
use Drago\Attr\AttributeDetectionException;
use Drago\Attr\Table;
use Drago\Database\Database;


#[Table(UserEntity::Table, UserEntity::ColumnId, class: UserEntity::class)]
class UserRepository
{
	/** @phpstan-use Database<UserEntity> */
	use Database;

	public function __construct(
		protected readonly Connection $connection,
	) {
	}


	/**
	 * Finds a user by their email.
	 * @throws Exception
	 * @throws AttributeDetectionException
	 */
	public function findUserByEmail(string $email): ?UserEntity
	{
		return $this->find(UserEntity::ColumnEmail, $email)
			->record();
	}


	/**
	 * Finds a user by their email or throws an exception if not found.
	 * @throws UserNotFoundException
	 * @throws Exception
	 * @throws AttributeDetectionException
	 */
	public function getUserByEmail(string $email): UserEntity
	{
		$user = $this->findUserByEmail($email);
		if ($user === null) {
			throw new UserNotFoundException("User with email '$email' was not found.", 1001);
		}
		return $user;
	}


	/**
	 * Finds a user by their token.
	 * @throws AttributeDetectionException
	 * @throws Exception
	 */
	public function findUserByToken(string $token): ?UserEntity
	{
		return $this->find(UserEntity::ColumnToken, $token)
			->record();
	}


	/** @return list<string> */
	public function getRolesByUser(int $userId): array
	{
		return [];
	}
}
