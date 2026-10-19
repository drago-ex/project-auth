<?php

declare(strict_types=1);

namespace App\Model\User;

use Dibi\Connection;
use Dibi\Exception;
use Dibi\Row;
use Nette\Utils\Random;


/**
 * One-time tokens sent by email. Only a hash is stored, so a leaked database does not reveal usable links.
 */
class UserTokenRepository
{
	public const string
		Table = 'user_tokens',
		TypeVerify = 'verify',
		TypeEmailChange = 'email-change';


	public function __construct(
		private readonly Connection $connection,
	) {
	}


	/**
	 * Creates a token and replaces the previous one of the same type, so only the latest link works.
	 * @throws Exception
	 */
	public function create(int $userId, string $type, int $lifetime, ?string $email = null): string
	{
		$token = Random::generate(40);
		$this->connection->delete(self::Table)
			->where('user_id = ? AND type = ?', $userId, $type)
			->execute();

		$this->connection->insert(self::Table, [
			'user_id' => $userId,
			'type' => $type,
			'token_hash' => self::hash($token),
			'email' => $email,
			'expires_at' => new \DateTimeImmutable("+$lifetime seconds"),
		])->execute();

		return $token;
	}


	/**
	 * Returns the token record and deletes it, or null when the token is unknown or expired.
	 * @return array{user_id: int, email: ?string}|null
	 * @throws Exception
	 */
	public function consume(string $token, string $type): ?array
	{
		$hash = self::hash($token);
		$row = $this->connection->select('user_id, email, expires_at')
			->from(self::Table)
			->where('token_hash = ? AND type = ?', $hash, $type)
			->fetch();

		if (!$row instanceof Row) {
			return null;
		}

		$this->connection->delete(self::Table)
			->where('token_hash = ?', $hash)
			->execute();

		$expiresAt = $row['expires_at'];
		if (!$expiresAt instanceof \DateTimeInterface || $expiresAt <= new \DateTimeImmutable) {
			return null;
		}

		$email = $row['email'];
		return ['user_id' => (int) $row['user_id'], 'email' => is_string($email) ? $email : null];
	}


	private static function hash(string $token): string
	{
		return hash('sha256', $token);
	}
}
