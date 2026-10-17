<?php

/**
 * Test: App\Model\User\UserAuthenticator
 */

declare(strict_types=1);

use App\Model\User\UserAuthenticator;
use App\Model\User\UserEntity;
use App\Model\User\UserRepository;
use Dibi\Connection;
use Nette\Security\AuthenticationException;
use Nette\Security\Passwords;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


test('Unknown email costs a password hash, so the response time does not reveal accounts', function () {
	$passwords = new class extends Passwords {
		public int $hashed = 0;


		public function hash(#[\SensitiveParameter] string $password): string
		{
			$this->hashed++;
			return parent::hash($password);
		}
	};

	$repository = new class (new Connection(['driver' => 'mysqli', 'lazy' => true])) extends UserRepository {
		public function findUserByEmail(string $email): ?UserEntity
		{
			return null;
		}
	};

	$authenticator = new UserAuthenticator($passwords, $repository);

	Assert::exception(
		fn() => $authenticator->authenticate('nobody@example.com', 'secret'),
		AuthenticationException::class,
	);
	Assert::same(1, $passwords->hashed);
});
