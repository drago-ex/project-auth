<?php

declare(strict_types=1);

namespace App\Presentation\Sign;

use App\Presentation\BasePresenter;
use App\Presentation\Sign\Recovery\RecoveryFactory;
use App\Presentation\Sign\Recovery\RecoveryThrottledException;
use App\Presentation\Sign\Recovery\SessionService;
use Drago\Application\UI\Alert;
use Drago\Form\Autocomplete;
use Nette\Application\Attributes\Persistent;
use Nette\Application\Attributes\Requires;
use Nette\Application\UI\Form;
use Nette\Neon\Exception;
use Nette\Security\AuthenticationException;
use Random\RandomException;
use RuntimeException;
use Throwable;


/** @property-read SignTemplate $template */
final class SignPresenter extends BasePresenter
{
	#[Persistent]
	public string $backlink = '';


	public function __construct(
		private readonly Factory $factory,
		private readonly SignUpFactory $signUpFactory,
		private readonly RecoveryFactory $recoveryFactory,
		private readonly SessionService $sessionService,
	) {
		parent::__construct();
	}


	public function actionIn(): void
	{
		$this->redrawSnippets();
	}


	public function actionUp(): void
	{
		$this->redrawSnippets();
	}


	public function actionRecovery(): void
	{
		$this->redrawSnippets();
	}


	public function actionOut(): void
	{
		$this->getUser()->logout();
		$this->redrawSnippets();
	}


	/**
	 * @throws Throwable
	 * @throws Exception
	 * @throws RandomException
	 */
	#[Requires(ajax: true)]
	public function handleResendRecovery(): void
	{
		try {
			$this->recoveryFactory->resendCode($this->lang);
			$this->flashMessage('If the address is registered, a new recovery code has been sent.', Alert::Success);

		} catch (RecoveryThrottledException $e) {
			$this->flashMessage($e->getMessage(), Alert::Danger);

		} catch (RuntimeException) {
			$this->flashMessage('Password recovery session has expired.', Alert::Danger);
		}

		$this->redrawSnippets();
	}


	#[Requires(ajax: true)]
	public function handleChangeRecoveryEmail(): void
	{
		$this->sessionService->reset();
		$this->redrawSnippets();
	}


	protected function beforeRender(): void
	{
		parent::beforeRender();
		if ($this->getAction() === 'recovery') {
			$token = $this->sessionService->createSignRecoveryToken();
			$this->template->signRecoveryToken = $token;
		}
	}


	protected function createComponentSignIn(): Form
	{
		$form = $this->factory->create();
		$form->addEmailField();
		$form->addPasswordField()
			->setAutocomplete(Autocomplete::CurrentPassword);

		$form->addSubmit('send', 'Sign in');
		$form->onSuccess[] = $this->processSignInForm(...);
		return $form;
	}


	protected function createComponentSignUp(): Form
	{
		$form = $this->signUpFactory->create();
		$form->onSuccess[] = function () {
			$this->flashMessage('Your registration has been successfully completed, you can now log in.', Alert::Success);
			$this->redirect('in');
		};
		return $form;
	}


	/**
	 * Creates and handles the password recovery request form.
	 * @throws Exception
	 * @throws Throwable
	 */
	protected function createComponentSignRecoveryRequest(): Form
	{
		$form = $this->recoveryFactory->createRequest($this->lang);
		$form->onSuccess[] = function () {
			$this->flashMessage('If the address is registered, a recovery code has been sent.', Alert::Success);
		};
		return $form;
	}


	protected function createComponentSignRecoveryCheckToken(): Form
	{
		$form = $this->recoveryFactory->createCheckToken();
		$form->onSuccess[] = function () {
			$this->flashMessage('Code check was successful.', Alert::Success);
		};
		return $form;
	}


	protected function createComponentSignRecoveryChangePassword(): Form
	{
		$form = $this->recoveryFactory->createChangePassword();
		$form->onSuccess[] = function () {
			$this->flashMessage('Password change was successful.', Alert::Success);
			$this->redirect('in');
		};
		return $form;
	}


	private function redrawSnippets(): void
	{
		$this->redrawControl('title');
		$this->redrawControl('content');
	}


	private function processSignInForm(Form $form, SignValues $values): void
	{
		try {
			$this->getUser()->login($values->email, $values->password);
			$this->restoreRequest($this->backlink);
			$this->redirect(':Backend:Admin:');
		} catch (AuthenticationException) {
			$form->addError('The email or password is incorrect.');
		}
	}
}
