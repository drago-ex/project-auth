<?php declare(strict_types=1);

namespace App\Presentation\Sign;

use App\Model\User\UserEntity;
use App\Presentation\Sign\Verification\VerificationService;
use Dibi\Connection;
use Dibi\UniqueConstraintViolationException;
use Drago\Form\Autocomplete;
use Exception;
use Nette\Application\UI\Form;
use Nette\Http\IRequest;
use Nette\Security\Passwords;
use Nette\Utils\AssertionException;
use Nette\Utils\Random;
use Nette\Utils\Validators;


readonly class SignUpFactory
{
	public const string LimitBucket = 'signup';

	public const int MaxAttempts = 10,
		LimitWindow = 3600;


	public function __construct(
		private Passwords $password,
		private Factory $factory,
		private Connection $connection,
		private RateLimiter $limiter,
		private IRequest $httpRequest,
		// Optional: the installer creates a verified administrator without sending an email.
		private ?VerificationService $verification = null,
	) {
	}


	public function create(string $lang = 'cs'): Form
	{
		$form = $this->factory->create();
		$form->addTextInput(SignUpValues::Username, 'Full name')
			->setPlaceholder('Full name')
			->setAutocomplete(Autocomplete::Name)
			->setRequired('Please enter your full name.')
			->addRule($form::MaxLength, 'The full name is too long (maximum %d characters).', UserEntity::ColumnUsernameSize);

		$form->addEmailField()
			->setDefaultValue('@')
			->addRule($form::MaxLength, 'The email address is too long (maximum %d characters).', UserEntity::ColumnEmailSize);

		$password = $form->addPasswordField()
			->setAutocomplete(Autocomplete::NewPassword);
		PasswordPolicy::apply($password);

		$form->addPasswordConfirmationField()
			->setAutocomplete(Autocomplete::Off);

		$form->addSubmit('send', 'Create account');
		$form->onSuccess[] = fn(Form $form, SignUpValues $values) => $this->processSignUpForm($form, $values, $lang);
		return $form;
	}


	/**
	 * Handles the successful submission of the form.
	 * @throws Exception
	 * @throws AssertionException
	 */
	private function processSignUpForm(Form $form, SignUpValues $values, string $lang): void
	{
		// Limit first: hashing the password is the expensive part.
		$ip = $this->httpRequest->getRemoteAddress() ?? 'unknown';
		if (!$this->limiter->isAllowed(self::LimitBucket, $ip, self::MaxAttempts)) {
			$form->addError('Too many registration attempts. Try again later.');
			return;
		}

		$this->limiter->hit(self::LimitBucket, $ip, self::LimitWindow);

		$values->password = $this->password->hash($values->password);
		$values->token = Random::generate(32);
		$values->offsetUnset(SignUpValues::Verify);

		Validators::assert($values->email, 'email');

		// Without verification (or in the installer) the account is usable at once.
		$data = $values->toArray();
		$data[UserEntity::ColumnVerifiedAt] = $this->verification?->isEnabled() ? null : new \DateTimeImmutable;

		try {
			$this->connection->insert(UserEntity::Table, $data)
				->execute();

			$this->verification?->registered($this->connection->getInsertId(), $values->email, $values->username, $lang);

		} catch (UniqueConstraintViolationException $e) {
			// With verification the form does not reveal that the address is registered, see alreadyRegistered().
			if ($e->getCode() === 1062 && $this->verification?->isEnabled()) {
				$this->verification->alreadyRegistered($values->email, $lang);
				return;
			}

			$message = match ($e->getCode()) {
				1062 => 'An account with this email address already exists. You can sign in or reset your password.',
				default => 'Unknown status code.',
			};
			$form->addError($message);
		}
	}
}
