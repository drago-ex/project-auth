<?php

declare(strict_types=1);

namespace App\UI\Backend\Sign\Recovery;

use App\UI\Backend\Sign\Factory;
use App\UI\Backend\Sign\User\UserRepository;
use Dibi\Exception;
use Drago\Attr\AttributeDetectionException;
use Drago\Form\Autocomplete;
use Drago\Localization\Translator;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\TextInput;
use Nette\Security\Passwords;


/**
 * Factory for creating password recovery forms and handling password recovery logic.
 * Provides methods for creating forms related to password recovery: request form, token check, and password change.
 */
class RecoveryFactory
{
	public Translator $translator;


	public function __construct(
		private readonly Factory $factory,
		private readonly SessionService $sessionService,
		private readonly UserRepository $userRepository,
		private readonly EmailService $emailService,
		private readonly Passwords $passwords,
	) {
	}


	/**
	 * Creates the password recovery request form.
	 */
	public function createRequest(): Form
	{
		$form = $this->factory->create();
		$form->addEmailField()
			->addRule([$this, 'emailCheck'], "We're sorry, but we don't know such an email address.");

		$form->addSubmit('send', 'Reset password');
		$form->onSuccess[] = $this->request(...);
		return $form;
	}


	/**
	 * Creates the form for checking the recovery token.
	 */
	public function createCheckToken(): Form
	{
		$form = $this->factory->create();
		$form->addTextInput(
			name: 'token',
			label: 'Code',
			placeholder: 'Enter the code from the email',
			required: 'Please enter the code from the email.',
		)->addRule([$this, 'tokenCheck'], 'The code entered is invalid.');

		$form->addSubmit('send', 'Continue password recovery');
		$form->onSuccess[] = $this->checkToken(...);
		return $form;
	}


	/**
	 * Checks if the entered token is valid.
	 *
	 * @param TextInput $input The input field for the token.
	 * @return bool True if the token is valid, false otherwise.
	 */
	public function tokenCheck(TextInput $input): bool
	{
		return $this->sessionService
			->isTokenValid($input->getValue());
	}


	/**
	 * @throws AttributeDetectionException
	 * @throws Exception
	 */
	public function emailCheck(TextInput $input): bool
	{
		$findEmail = $this->userRepository->findUserByEmail($input->getValue());
		return (bool) $findEmail;
	}


	/**
	 * Creates the form for changing the password.
	 */
	public function createChangePassword(): Form
	{
		$form = $this->factory->create();
		$form->addPasswordField()
			->setAutocomplete(Autocomplete::NewPassword);

		$form->addPasswordConfirmationField()
			->setAutocomplete(Autocomplete::NewPassword);

		$form->addSubmit('send', 'Change your password');
		$form->onSuccess[] = $this->changePassword(...);
		return $form;
	}


	/**
	 * Handles the password recovery request form submission.
	 * Generates a recovery token if the email exists in the database.
	 */
	public function request(Form $form): void
	{
		try {
			$values = $form->getValues();
			$email = $values['email'];

			// We will create a token and save the email.
			$this->sessionService->generateToken($email);

			// We will create a sending email.
			$request = $this->emailService;
			$request->email = $email;
			$request->token = $this->sessionService->getToken();
			$request->setTranslator($this->translator);
			$request->sendEmail();

		} catch (\Throwable $e) {
			$message = 'Unknown status code.';
			$form->addError($message);
		}
	}


	/**
	 * Handles the token check form submission.
	 */
	public function checkToken(): void
	{
		$this->sessionService
			->setTokenCheck();
	}


	/**
	 * Handles the password change form submission.
	 * Removes the token from the session after the password is successfully changed.
	 */
	public function changePassword(Form $form): void
	{
		try {
			$password = $form->getValues()['password'];
			$user = $this->userRepository->findUserByEmail(
				$this->sessionService->getEmail(),
			);

			// Save password change.
			$user->password = $this->passwords->hash($password);
			$this->userRepository->save($user);

			// We delete the token and the control flag.
			$this->sessionService->removeToken();

		} catch (\Throwable $e) {
			$form->addError('An error occurred during password change.');
		}
	}
}
