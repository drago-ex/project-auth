<?php

declare(strict_types=1);

namespace App\Presentation\Sign;

use App\Model\User\UserEntity;
use Dibi\Connection;
use Dibi\UniqueConstraintViolationException;
use Drago\Form\Autocomplete;
use Drago\Form\Rules\PasswordRules;
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

	public const int
		MaxAttempts = 10,
		LimitWindow = 3600;


	public function __construct(
		private Passwords $password,
		private Factory $factory,
		private Connection $connection,
		private RateLimiter $limiter,
		private IRequest $httpRequest,
	) {
	}


	public function create(): Form
	{
		$form = $this->factory->create();
		$form->addTextInput(SignUpValues::Username, 'Full name')
			->setPlaceholder('Full name')
			->setAutocomplete(Autocomplete::Name)
			->setRequired('Please enter your full name.')
			->addRule($form::MaxLength, 'The full name is too long (maximum %d characters).', 50);

		$form->addEmailField()
			->setDefaultValue('@')
			->addRule($form::MaxLength, 'The email address is too long (maximum %d characters).', 254);

		$form->addPasswordField()
			->setAutocomplete(Autocomplete::NewPassword)
			->addRule($form::MinLength, 'Password must be at least %d characters long.', 8)
			->addRule($form::Pattern, PasswordRules::StrongMessage, PasswordRules::StrongPattern);

		$form->addPasswordConfirmationField()
			->setAutocomplete(Autocomplete::Off);

		$form->addSubmit('send', 'Create account');
		$form->onSuccess[] = $this->processSignUpForm(...);
		return $form;
	}


	/**
	 * Handles the successful submission of the form.
	 * @throws Exception
	 * @throws AssertionException
	 */
	private function processSignUpForm(Form $form, SignUpValues $values): void
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

		try {
			$this->connection->insert(UserEntity::Table, $values->toArray())
				->execute();

		} catch (UniqueConstraintViolationException $e) {
			$message = match ($e->getCode()) {
				1062 => 'An account with this email address already exists. You can sign in or reset your password.',
				default => 'Unknown status code.',
			};
			$form->addError($message);
		}
	}
}
