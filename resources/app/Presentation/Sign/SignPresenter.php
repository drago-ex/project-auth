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
use Nette\Http\FetchSite;
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
		private readonly SignInLimiter $signInLimiter,
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


	/**
	 * A sign-out link on this site signs out at once. A link from another site, or a prefetch,
	 * only shows the sign-out button, so another site cannot sign the user out (as Nette protects signals).
	 */
	public function actionOut(): void
	{
		if ($this->getUser()->isLoggedIn() && $this->isSameOriginNavigation()) {
			$this->getUser()->logout();
		}

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
		$form = $this->signUpFactory->create($this->lang);
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


	protected function createComponentSignOut(): Form
	{
		$form = $this->factory->create();
		$form->addSubmit('send', 'Sign out');
		$form->onSuccess[] = function () {
			$this->getUser()->logout();
			$this->redirect('out');
		};
		return $form;
	}


	private function isSameOriginNavigation(): bool
	{
		$request = $this->getHttpRequest();
		$purpose = (string) ($request->getHeader('Sec-Purpose') ?? $request->getHeader('Purpose'));

		return $request->isFrom(FetchSite::SameOrigin) && !str_contains($purpose, 'prefetch');
	}


	private function redrawSnippets(): void
	{
		$this->redrawControl('title');
		$this->redrawControl('content');
	}


	private function processSignInForm(Form $form, SignValues $values): void
	{
		// Checked before login, so a blocked attempt does not even verify the password.
		$ip = $this->getHttpRequest()->getRemoteAddress() ?? 'unknown';
		if (!$this->signInLimiter->isAllowed($values->email, $ip)) {
			$form->addError('Too many sign-in attempts. Try again later.');
			return;
		}

		try {
			$this->getUser()->login($values->email, $values->password);
		} catch (AuthenticationException) {
			$this->signInLimiter->registerFailure($values->email, $ip);
			$form->addError('The email or password is incorrect.');
			return;
		}

		$this->signInLimiter->registerSuccess($values->email);
		$this->restoreRequest($this->backlink);
		$this->redirect(':Backend:Admin:');
	}
}
