<?php

declare(strict_types=1);

namespace App\Presentation\Sign\Recovery;

use App\Model\User\UserRepository;
use App\Presentation\Sign\Factory;
use Dibi\Exception;
use Drago\Attr\AttributeDetectionException;
use Drago\Form\Autocomplete;
use Drago\Form\Rules\PasswordRules;
use Nette\Application\UI\Form;
use Nette\Forms\Control;
use Nette\Forms\Controls\BaseControl;
use Nette\Security\Passwords;
use Nette\Utils\Random;
use Random\RandomException;
use Throwable;


readonly class RecoveryFactory
{
	public function __construct(
		private Factory $factory,
		private SessionService $sessionService,
		private UserRepository $userRepository,
		private EmailService $emailService,
		private Passwords $passwords,
	) {
	}


	public function createRequest(string $lang): Form
	{
		$form = $this->factory->create();
		$form->addEmailField()
			->addRule($this->emailCheck(...), "We're sorry, but we don't know such an email address.");

		$form->addSubmit('send', 'Send recovery code');
		$form->onSuccess[] = fn(Form $form) => $this->request($form, $lang);
		return $form;
	}


	public function createCheckToken(): Form
	{
		$form = $this->factory->create();
		// Text input (not integer): the code may start with zero, e.g. 012345.
		$form->addTextInput('token', 'Code')
			->setAutocomplete(Autocomplete::OneTimeCode)
			->setHtmlAttribute('inputmode', 'numeric')
			->setRequired('Please enter the code from the email.')
			->addRule($form::Length, 'The code must be exactly %d digits.', 6)
			->addRule($form::Pattern, 'The code must contain digits only.', '[0-9]+');

		$form->addSubmit('send', 'Verify code');
		$form->onValidate[] = $this->validateToken(...);
		$form->onSuccess[] = $this->checkToken(...);
		return $form;
	}


	public function createChangePassword(): Form
	{
		$form = $this->factory->create();
		$form->addPasswordField()
			->setAutocomplete(Autocomplete::NewPassword)
			->addRule($form::MinLength, 'Password must be at least %d characters long.', 8)
			->addRule($form::Pattern, PasswordRules::StrongMessage, PasswordRules::StrongPattern);

		$form->addPasswordConfirmationField()
			->setAutocomplete(Autocomplete::Off);

		$form->addSubmit('send', 'Change password');
		$form->onSuccess[] = $this->changePassword(...);
		return $form;
	}


	/**
	 * @throws Throwable
	 * @throws RandomException
	 * @throws \Nette\Neon\Exception
	 */
	public function resendCode(string $lang): void
	{
		$email = $this->sessionService->getEmail();
		$token = $this->sessionService->generateToken($email);
		$this->emailService->sendEmail($email, $token, $lang);
	}


	private function validateToken(Form $form): void
	{
		$input = $form->getComponent('token');
		assert($input instanceof BaseControl);

		if ($input->getErrors() !== []) {
			return;
		}

		if ($this->sessionService->isTokenValid((string) $input->getValue())) {
			return;
		}

		$input->addError($this->sessionService->hasAttemptsRemaining()
			? 'The code entered is invalid.'
			: 'Too many incorrect attempts. Try again later.');
	}


	/**
	 * Checks if the email address exists.
	 * @throws AttributeDetectionException
	 * @throws Exception
	 */
	private function emailCheck(Control $input): bool
	{
		$findEmail = $this->userRepository->findUserByEmail($input->getValue());
		return (bool) $findEmail;
	}


	private function request(Form $form, string $lang): void
	{
		try {
			$values = $form->getValues();
			$email = (string) $values['email'];
			$token = $this->sessionService
				->generateToken($email);

			$request = $this->emailService;
			$request->sendEmail($email, $token, $lang);

		} catch (RecoveryThrottledException $e) {
			$form->addError($e->getMessage());

		} catch (Throwable $e) {
			$message = 'Unknown status code.';
			$form->addError($message);
		}
	}


	private function checkToken(): void
	{
		$this->sessionService
			->setTokenCheck();
	}


	private function changePassword(Form $form): void
	{
		// The form component can be submitted directly, regardless of what the template shows.
		if (!$this->sessionService->isTokenChecked()) {
			$form->addError('Please verify the code first.');
			return;
		}

		try {
			$password = (string) $form->getValues()['password'];
			$email = $this->sessionService->getEmail();
			$user = $this->userRepository->getUserByEmail($email);

			$user->password = $this->passwords->hash($password);
			$user->token = Random::generate(32); // invalidates all existing sessions
			$this->userRepository->save($user);
			$this->sessionService->removeToken();

		} catch (Throwable $e) {
			$message = match ($e->getCode()) {
				1001 => 'User with email was not found.',
				default => 'Unknown status code.',
			};
			$form->addError($message);
		}
	}
}
