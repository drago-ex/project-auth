<?php declare(strict_types=1);

/**
 * Test: App\Model\User\UserAuthenticator
 */

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


		public function hash(#[SensitiveParameter] string $password): string
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


test('An unconfirmed account cannot sign in, but only after the right password', function () {
	$passwords = new Passwords;
	$user = new UserEntity;
	$user->id = 1;
	$user->email = 'new@example.com';
	$user->password = $passwords->hash('correct horse battery staple');
	$user->verified_at = null;

	$repository = new class (new Connection(['driver' => 'mysqli', 'lazy' => true]), $user) extends UserRepository {
		public function __construct(
			Connection $connection,
			private UserEntity $user,
		) {
			parent::__construct($connection);
		}


		public function findUserByEmail(string $email): ?UserEntity
		{
			return $this->user;
		}
	};

	$authenticator = new UserAuthenticator($passwords, $repository);

	$e = Assert::exception(
		fn() => $authenticator->authenticate('new@example.com', 'wrong password'),
		AuthenticationException::class,
	);
	Assert::notSame(Nette\Security\Authenticator::NotApproved, $e->getCode());

	Assert::exception(
		fn() => $authenticator->authenticate('new@example.com', 'correct horse battery staple'),
		AuthenticationException::class,
		null,
		Nette\Security\Authenticator::NotApproved,
	);
});
