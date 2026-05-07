<?php

declare(strict_types=1);

namespace App\UI\Backend\Sign\User;

use Dibi\Connection;
use Dibi\Exception;
use Drago\Attr\AttributeDetectionException;
use Drago\Attr\Table;
use Drago\Database\Database;


/**
 * Repository for accessing user data in the database, specifically for operations related to user sign-in and recovery.
 * It handles database operations for finding a user by email.
 */
#[Table(UserEntity::Table, UserEntity::ColumnId, class: UserEntity::class)]
class UserRepository
{
	use Database;

	public function __construct(
		private readonly Connection $connection,
	) {
	}


	/**
	 * Finds a user by their email.
	 *
	 * @throws Exception If there is an error while finding the user.
	 * @throws AttributeDetectionException If there is an error while finding attributes.
	 */
	public function findUserByEmail(string $email): array|UserEntity|null
	{
		return $this->find(UserEntity::ColumnEmail, $email)
			->record();
	}


	/**
	 * Find user by token.
	 *
	 * @throws AttributeDetectionException If there is an error while finding attributes.
	 * @throws Exception If there is an error while finding the user.
	 */
	public function findUserByToken(string $token): array|UserEntity|null
	{
		return $this->find(UserEntity::ColumnToken, $token)
			->record();
	}


	/**
	 * Finds the roles of a user.
	 */
	public function getRolesByUser(int $userId): array
	{
		return [];
	}
}
