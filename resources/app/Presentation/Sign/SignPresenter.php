<?php

declare(strict_types=1);

namespace App\Presentation\Sign;

use App\Presentation\BasePresenter;
use App\Presentation\Sign\Recovery\RecoveryFactory;
use App\Presentation\Sign\Recovery\SessionService;
use Drago\Application\UI\Alert;
use Drago\Form\Autocomplete;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;
use Nette\Neon\Exception;
use Nette\Security\AuthenticationException;
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


	private function redrawSnippets(): void
	{
		$this->redrawControl('title');
		$this->redrawControl('content');
	}


	protected function beforeRender(): void
	{
		parent::beforeRender();

		if ($this->getAction() === 'recovery') {
			$this->template->signRecoveryToken = $this->sessionService->createSignRecoveryToken();
		}

		if ($this->isAjax()) {
			$this->redrawSnippets();
		}
	}


	protected function createComponentSignIn(): Form
	{
		$form = $this->factory->create();
		$form->addEmailField();
		$form->addPasswordField()
			->setAutocomplete(Autocomplete::CurrentPassword);

		$form->addSubmit('send', 'Sign in');
		$form->onSuccess[] = $this->success(...);
		return $form;
	}


	private function success(Form $form, SignValues $values): void
	{
		try {
			$this->getUser()->login($values->email, $values->password);
			$this->restoreRequest($this->backlink);
			$this->redirect(':Backend:Admin:');
		} catch (AuthenticationException) {
			$form->addError('The email or password is incorrect.');
		}
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
			$this->flashMessage('A password recovery code has been sent to your email.', Alert::Success);
		};
		return $form;
	}


	public function handleResendRecovery(): void
	{
		$this->recoveryFactory->resendCode($this->lang);
		$this->flashMessage('A new password recovery code has been sent to your email.', Alert::Success);

		if (!$this->isAjax()) {
			$this->redirect('recovery');
		}

		$this->redrawSnippets();
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


	public function actionOut(): void
	{
		$this->getUser()->logout();
	}
}
