<?php

declare(strict_types=1);

namespace App\Presentation\Sign\Recovery;

use App\Model\User\UserRepository;
use App\Presentation\Sign\Factory;
use Drago\Form\Autocomplete;
use Drago\Form\Rules\PasswordRules;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;
use Nette\Http\IRequest;
use Nette\Neon\Exception;
use Nette\Security\Passwords;
use Nette\Utils\Random;
use Random\RandomException;
use Throwable;
use Tracy\Debugger;
use Tracy\ILogger;


readonly class RecoveryFactory
{
	public function __construct(
		private Factory $factory,
		private SessionService $sessionService,
		private UserRepository $userRepository,
		private EmailService $emailService,
		private Passwords $passwords,
		private RecoveryLimiter $limiter,
		private IRequest $httpRequest,
	) {
	}


	public function createRequest(string $lang): Form
	{
		$form = $this->factory->create();
		$form->addEmailField();

		$form->addSubmit('send', 'Send recovery code');
		$form->onSuccess[] = fn(Form $form) => $this->request($form, $lang);
		return $form;
	}


	public function createCheckToken(): Form
	{
		$form = $this->factory->create();
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
	 * @throws Exception
	 */
	public function resendCode(string $lang): void
	{
		$this->issueCode($this->sessionService->getEmail(), $lang);
	}


	private function validateToken(Form $form): void
	{
		$input = $form->getComponent('token');
		assert($input instanceof BaseControl);

		if ($input->getErrors() !== []) {
			return;
		}

		$email = $this->sessionService->findEmail();
		if ($email === null) {
			$input->addError('The code entered is invalid.');
			return;
		}

		if (!$this->limiter->isVerifyAllowed($email)) {
			$input->addError('Too many incorrect attempts. Try again later.');
			return;
		}

		if ($this->sessionService->isTokenValid((string) $input->getValue())) {
			return;
		}

		$this->limiter->registerFailure($email);
		$input->addError($this->sessionService->hasAttemptsRemaining() && $this->limiter->isVerifyAllowed($email)
			? 'The code entered is invalid.'
			: 'Too many incorrect attempts. Try again later.');
	}


	/**
	 * Issues a recovery code. Unknown addresses get exactly the same response as known ones,
	 * only the email is not sent, so the form cannot be used to find out who has an account.
	 * @throws RecoveryThrottledException
	 * @throws RandomException
	 * @throws Throwable
	 */
	private function issueCode(string $email, string $lang): void
	{
		$ip = $this->httpRequest->getRemoteAddress() ?? 'unknown';
		if (!$this->limiter->isRequestAllowed($email) || !$this->limiter->isIpAllowed($ip)) {
			throw new RecoveryThrottledException('Too many recovery requests. Try again later.');
		}

		$token = $this->sessionService->generateToken($email);
		$this->limiter->registerRequest($email);
		$this->limiter->registerIp($ip);

		if ($this->userRepository->findUserByEmail($email) === null) {
			return;
		}

		$this->emailService->sendEmail($email, $token, $lang);
	}


	private function request(Form $form, string $lang): void
	{
		try {
			$values = $form->getValues();
			$this->issueCode((string) $values['email'], $lang);

		} catch (RecoveryThrottledException $e) {
			$form->addError($e->getMessage());

		} catch (Throwable $e) {
			Debugger::log($e, ILogger::EXCEPTION);
			$form->addError('Unknown status code.');
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
			if ($e->getCode() !== 1001) {
				Debugger::log($e, ILogger::EXCEPTION);
			}

			$message = match ($e->getCode()) {
				1001 => 'User with email was not found.',
				default => 'Unknown status code.',
			};
			$form->addError($message);
		}
	}
}
