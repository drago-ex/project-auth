<?php declare(strict_types=1);

/**
 * Test: App\Presentation\Sign\Verification\VerificationService
 */

use App\Model\User\UserEntity;
use App\Model\User\UserRepository;
use App\Model\User\UserTokenRepository;
use App\Presentation\Sign\Email\EmailSender;
use App\Presentation\Sign\RateLimiter;
use App\Presentation\Sign\Verification\ConfirmResult;
use App\Presentation\Sign\Verification\VerificationService;
use Dibi\Connection;
use Drago\Database\ExtraFluent;
use Nette\Application\LinkGenerator;
use Nette\Application\Routers\Route;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Http\UrlScript;
use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';


function lazyConnection(): Connection
{
	return new Connection(['driver' => 'mysqli', 'lazy' => true]);
}


class MemoryUsers extends UserRepository
{
	/** @var array<int, UserEntity> */
	public array $users = [];


	public function __construct()
	{
	}


	public function add(int $id, string $email, ?DateTimeInterface $verifiedAt): UserEntity
	{
		$user = new UserEntity;
		$user->id = $id;
		$user->username = "User $id";
		$user->email = $email;
		$user->password = 'hash';
		$user->token = 'token';
		$user->verified_at = $verifiedAt;
		return $this->users[$id] = $user;
	}


	public function findUserByEmail(string $email): ?UserEntity
	{
		foreach ($this->users as $user) {
			if ($user->email === $email) {
				return $user;
			}
		}

		return null;
	}


	public function get(int $id): ExtraFluent
	{
		$user = $this->users[$id] ?? null;
		return new class (lazyConnection(), $user) extends ExtraFluent {
			public function __construct(
				Connection $connection,
				private ?UserEntity $user,
			) {
				parent::__construct($connection);
			}


			public function record(): ?Dibi\Row
			{
				return $this->user;
			}
		};
	}


	public function save(mixed $args): Dibi\Result|int|null
	{
		return 1;
	}
}


class MemoryTokens extends UserTokenRepository
{
	/** @var array<string, array{user_id: int, type: string, email: ?string, expires: int}> */
	public array $tokens = [];


	public function __construct()
	{
	}


	public function create(int $userId, string $type, int $lifetime, ?string $email = null): string
	{
		$token = bin2hex(random_bytes(8));
		$this->tokens[$token] = ['user_id' => $userId, 'type' => $type, 'email' => $email, 'expires' => time() + $lifetime];
		return $token;
	}


	public function consume(string $token, string $type): ?array
	{
		$record = $this->tokens[$token] ?? null;
		if ($record === null || $record['type'] !== $type) {
			return null;
		}

		unset($this->tokens[$token]);
		return $record['expires'] > time() ? ['user_id' => $record['user_id'], 'email' => $record['email']] : null;
	}
}


/** @return array{VerificationService, MemoryUsers, MemoryTokens, ArrayObject<int, array{string, string}>} */
function createService(bool $enabled = true): array
{
	$sent = new ArrayObject;
	$sender = new readonly class ($sent) extends EmailSender {
		public function __construct(
			private ArrayObject $sent,
		) {
		}


		public function send(string $to, string $subject, string $file, array $parameters, string $lang): void
		{
			$this->sent[] = [$to, basename($file)];
		}
	};

	$links = new LinkGenerator(new Route('<presenter>/<action>'), new UrlScript('http://localhost/'));
	$users = new MemoryUsers;
	$tokens = new MemoryTokens;
	$service = new VerificationService($enabled, $users, $tokens, $sender, $links, new RateLimiter(new MemoryStorage));
	return [$service, $users, $tokens, $sent];
}


test('A new account gets a confirmation link, and the link confirms it once', function () {
	[$service, $users, $tokens, $sent] = createService();
	$user = $users->add(1, 'new@example.com', null);

	$service->registered(1, 'new@example.com', 'User 1', 'en');
	Assert::same([['new@example.com', 'verify.latte']], $sent->getArrayCopy());

	$token = array_key_first($tokens->tokens);
	Assert::same(ConfirmResult::Verified, $service->confirm($token, 'en'));
	Assert::type(DateTimeInterface::class, $user->verified_at);
	Assert::same(ConfirmResult::Invalid, $service->confirm($token, 'en'));
});


test('Without verification, a new account gets a welcome email instead', function () {
	[$service, $users, $tokens, $sent] = createService(enabled: false);

	$service->registered(1, 'new@example.com', 'User 1', 'en');
	Assert::same([['new@example.com', 'sign-up.latte']], $sent->getArrayCopy());
	Assert::same([], $tokens->tokens);
});


test('A registration with a registered address only emails the owner, at most three times an hour', function () {
	[$service, , $tokens, $sent] = createService();

	for ($i = 0; $i < 5; $i++) {
		$service->alreadyRegistered('taken@example.com', 'en');
	}

	Assert::same(array_fill(0, 3, ['taken@example.com', 'account-exists.latte']), $sent->getArrayCopy());
	Assert::same([], $tokens->tokens);

	// another address has its own limit
	$service->alreadyRegistered('other@example.com', 'en');
	Assert::count(4, $sent);
});


test('An unknown or expired link is invalid', function () {
	[$service, $users, $tokens] = createService();
	$users->add(1, 'new@example.com', null);
	$tokens->tokens['old'] = ['user_id' => 1, 'type' => UserTokenRepository::TypeVerify, 'email' => null, 'expires' => time() - 1];

	Assert::same(ConfirmResult::Invalid, $service->confirm('unknown', 'en'));
	Assert::same(ConfirmResult::Invalid, $service->confirm('old', 'en'));
	Assert::same(ConfirmResult::Invalid, $service->confirm('', 'en'));
});


test('A new email address applies only after its link is opened, and the previous address is notified', function () {
	[$service, $users, $tokens, $sent] = createService();
	$user = $users->add(1, 'old@example.com', new DateTimeImmutable);

	Assert::true($service->changeEmail($user, 'new@example.com', 'en'));
	Assert::same('old@example.com', $user->email);
	Assert::same([['new@example.com', 'email-change.latte']], $sent->getArrayCopy());

	Assert::same(ConfirmResult::EmailChanged, $service->confirm(array_key_first($tokens->tokens), 'en'));
	Assert::same('new@example.com', $user->email);
	Assert::same(['old@example.com', 'email-changed.latte'], $sent[1]);
});


test('An email address taken by another account in the meantime is not applied', function () {
	[$service, $users, $tokens] = createService();
	$user = $users->add(1, 'old@example.com', new DateTimeImmutable);
	$service->changeEmail($user, 'new@example.com', 'en');
	$users->add(2, 'new@example.com', new DateTimeImmutable);

	Assert::same(ConfirmResult::EmailTaken, $service->confirm(array_key_first($tokens->tokens), 'en'));
	Assert::same('old@example.com', $user->email);
});


test('Without verification, an email change applies at once and notifies the previous address', function () {
	[$service, $users, $tokens, $sent] = createService(enabled: false);
	$user = $users->add(1, 'old@example.com', new DateTimeImmutable);

	Assert::false($service->changeEmail($user, 'new@example.com', 'en'));
	Assert::same('new@example.com', $user->email);
	Assert::same([['old@example.com', 'email-changed.latte']], $sent->getArrayCopy());
});


test('Resending is limited and sends a link only to unconfirmed accounts', function () {
	[$service, $users, , $sent] = createService();
	$users->add(1, 'new@example.com', null);
	$users->add(2, 'done@example.com', new DateTimeImmutable);

	Assert::true($service->resend('done@example.com', '192.0.2.1', 'en'));
	Assert::true($service->resend('nobody@example.com', '192.0.2.1', 'en'));
	Assert::same([], $sent->getArrayCopy());

	for ($i = 0; $i < 3; $i++) {
		Assert::true($service->resend('new@example.com', '192.0.2.1', 'en'));
	}

	Assert::false($service->resend('new@example.com', '192.0.2.1', 'en'));
	Assert::count(3, $sent);
});
