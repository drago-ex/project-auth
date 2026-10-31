<?php declare(strict_types=1);

/**
 * Test: password recovery flow (App\Presentation\Sign\Recovery)
 */

use App\Model\User\UserEntity;
use App\Model\User\UserNotFoundException;
use App\Model\User\UserRepository;
use App\Presentation\Sign\Email\EmailSender;
use App\Presentation\Sign\Factory;
use App\Presentation\Sign\RateLimiter;
use App\Presentation\Sign\Recovery\RecoveryFactory;
use App\Presentation\Sign\Recovery\RecoveryLimiter;
use App\Presentation\Sign\Recovery\SessionService;
use Nette\Application\UI\Form;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Http;
use Nette\Localization\Translator;
use Nette\Security\Passwords;
use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';


class MemoryUserRepository extends UserRepository
{
	public MemoryTable $memory;


	public function __construct(
		private UserEntity $user,
	) {
		$this->table = $this->memory = new MemoryTable;
	}


	public function getUserByEmail(string $email): UserEntity
	{
		if ($email !== $this->user->email) {
			throw new UserNotFoundException('not found', 1001);
		}

		return $this->user;
	}
}


readonly class NullEmailSender extends EmailSender
{
	public function __construct()
	{
	}


	public function send(string $to, string $subject, string $file, array $parameters, string $lang): void
	{
	}
}


/** @return array{RecoveryFactory, SessionService, MemoryUserRepository} */
function createRecovery(): array
{
	$request = new Http\Request(new Http\UrlScript('http://localhost/'));
	$sessionService = new SessionService(new Http\Session($request, new Http\Response));
	$sessionService->removeToken(); // tests share the native PHP session

	$user = new UserEntity;
	$user->id = 1;
	$user->email = 'user@example.com';
	$user->password = 'old-hash';
	$user->token = 'old-token';
	$repository = new MemoryUserRepository($user);

	$translator = new class implements Translator {
		public function translate(string|Stringable $message, mixed ...$parameters): string
		{
			return (string) $message;
		}
	};

	$factory = new RecoveryFactory(
		new Factory($translator),
		$sessionService,
		$repository,
		new NullEmailSender,
		new Passwords,
		new RecoveryLimiter(new RateLimiter(new MemoryStorage)),
		$request,
	);

	return [$factory, $sessionService, $repository];
}


function submitChangePassword(RecoveryFactory $factory, string $password): Form
{
	$form = $factory->createChangePassword();

	// The form is anchored to a presenter, as in the application.
	$presenter = new class extends Nette\Application\UI\Presenter {
	};
	$presenter->injectPrimary(new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response);
	$presenter->addComponent($form, 'changePassword');

	$form->setValues(['password' => $password, 'verify' => $password]);
	$form->validate();
	foreach ($form->onSuccess as $handler) {
		$handler($form, $form->getValues());
	}

	return $form;
}


test('The password cannot be changed before the code is verified', function () {
	[$factory, $sessionService, $repository] = createRecovery();
	$sessionService->generateToken('user@example.com');

	$form = submitChangePassword($factory, 'NewSecret123!');

	Assert::same(['Please verify the code first.'], $form->getErrors());
	Assert::count(0, $repository->memory->saved);
});


test('After the code is verified the password and the session token are changed', function () {
	[$factory, $sessionService, $repository] = createRecovery();
	$code = $sessionService->generateToken('user@example.com');
	Assert::true($sessionService->isTokenValid($code));
	$sessionService->setTokenCheck();

	$form = submitChangePassword($factory, 'NewSecret123!');

	Assert::same([], $form->getErrors());
	Assert::count(1, $repository->memory->saved);
	Assert::true((new Passwords)->verify('NewSecret123!', $repository->memory->saved[0]->password));
	Assert::notSame('old-token', $repository->memory->saved[0]->token);
	Assert::false($sessionService->isTokenChecked(), 'the recovery session is closed');
});


test('A wrong code is counted and the limit blocks further guesses', function () {
	[, $sessionService] = createRecovery();
	$code = $sessionService->generateToken('user@example.com');
	$wrong = $code === '000000' ? '111111' : '000000';

	for ($attempt = 0; $attempt < 5; $attempt++) {
		Assert::false($sessionService->isTokenValid($wrong));
	}

	Assert::false($sessionService->hasAttemptsRemaining());
	Assert::false($sessionService->isTokenValid($code), 'even the right code is refused after the limit');
});


test('A verified code cannot be used again', function () {
	[, $sessionService] = createRecovery();
	$code = $sessionService->generateToken('user@example.com');
	Assert::true($sessionService->isTokenValid($code));
	$sessionService->setTokenCheck();

	Assert::false($sessionService->isTokenValid($code));
});
