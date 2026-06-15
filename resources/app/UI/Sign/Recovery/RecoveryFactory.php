<?php

declare(strict_types=1);

namespace App\UI\Sign\Recovery;

use App\UI\Sign\Factory;
use App\UI\Sign\User\UserRepository;
use Dibi\Exception;
use Drago\Attr\AttributeDetectionException;
use Drago\Form\Autocomplete;
use Drago\Form\Rules\PasswordRules;
use Nette\Application\UI\Form;
use Nette\Forms\Control;
use Nette\Security\Passwords;


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


	public function createRequest(): Form
	{
		$form = $this->factory->create();
		$form->addEmailField()
			->addRule($this->emailCheck(...), "We're sorry, but we don't know such an email address.");

		$form->addSubmit('send', 'Reset password');
		$form->onSuccess[] = $this->request(...);
		return $form;
	}


	public function createCheckToken(): Form
	{
		$form = $this->factory->create();
		$form->addTextInput('token', 'Code')
			->addRule($this->tokenCheck(...), 'The code entered is invalid.')
			->setPlaceholder('Enter the code from the email')
			->setRequired('Please enter the code from the email.')
			->setAutocomplete(Autocomplete::Off);

		$form->addSubmit('send', 'Continue password recovery');
		$form->onSuccess[] = $this->checkToken(...);
		return $form;
	}


	private function tokenCheck(Control $input): bool
	{
		return $this->sessionService
			->isTokenValid($input->getValue());
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


	public function createChangePassword(): Form
	{
		$form = $this->factory->create();
		$form->addPasswordField()
			->setAutocomplete(Autocomplete::NewPassword)
			->addRule($form::MinLength, 'Password must be at least %d characters long.', 8)
			->addRule($form::Pattern, PasswordRules::StrongMessage, PasswordRules::StrongPattern);

		$form->addPasswordConfirmationField()
			->setAutocomplete(Autocomplete::Off);

		$form->addSubmit('send', 'Change your password');
		$form->onSuccess[] = $this->changePassword(...);
		return $form;
	}


	private function request(Form $form): void
	{
		try {
			$values = $form->getValues();
			$email = (string) $values['email'];
			$token = $this->sessionService
				->generateToken($email);

			$request = $this->emailService;
			$request->sendEmail($email, $token);

		} catch (\Throwable $e) {
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
		try {
			$password = (string) $form->getValues()['password'];
			$email = $this->sessionService->getEmail();
			$user = $this->userRepository->getUserByEmail($email);

			$user->password = $this->passwords->hash($password);
			$this->userRepository->save($user);
			$this->sessionService->removeToken();

		} catch (\Throwable $e) {
			$message = match ($e->getCode()) {
				1001 => 'User with email was not found.',
				default => 'Unknown status code.',
			};
			$form->addError($message);
		}
	}
}
