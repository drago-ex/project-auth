<?php declare(strict_types=1);

namespace App\Presentation\Sign;

use App\Presentation\BasePresenter;
use App\Presentation\Sign\Recovery\RecoveryFactory;
use App\Presentation\Sign\Recovery\RecoveryThrottledException;
use App\Presentation\Sign\Recovery\SessionService;
use App\Presentation\Sign\Verification\ConfirmResult;
use App\Presentation\Sign\Verification\VerificationService;
use Drago\Application\UI\Alert;
use Drago\Form\Autocomplete;
use Nette\Application\Attributes\Persistent;
use Nette\Application\Attributes\Requires;
use Nette\Application\UI\Form;
use Nette\Http\FetchSite;
use Nette\InvalidStateException;
use Nette\Neon\Exception;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Random\RandomException;
use RuntimeException;
use Throwable;


/** @property-read SignTemplate $template */
final class SignPresenter extends BasePresenter
{
	/** Where a signed-in user goes after signing in or confirming an email link; change it to the project's own page. */
	private const string SignedInDestination = ':Backend:Admin:';

	/** Where a signed-in user goes who may not open SignedInDestination (e.g. a role without the administration). */
	private const string FallbackDestination = ':Front:Home:';

	#[Persistent]
	public string $backlink = '';


	public function __construct(
		private readonly Factory $factory,
		private readonly SignUpFactory $signUpFactory,
		private readonly RecoveryFactory $recoveryFactory,
		private readonly SessionService $sessionService,
		private readonly SignInLimiter $signInLimiter,
		private readonly VerificationService $verification,
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


	public function actionVerification(): void
	{
		$this->redrawSnippets();
	}


	/**
	 * Applies a link from an account email: confirms a new account or a changed email address.
	 * @throws Throwable
	 */
	public function actionVerify(string $token = ''): void
	{
		$result = $this->verification->confirm($token, $this->lang);
		match ($result) {
			ConfirmResult::Verified => $this->flashMessage('Your email address has been confirmed. You can sign in now.', Alert::Success),
			ConfirmResult::EmailChanged => $this->flashMessage('Your email address has been changed.', Alert::Success),
			ConfirmResult::EmailTaken => $this->flashMessage('This email address is already used by another account.', Alert::Danger),
			ConfirmResult::Invalid => $this->flashMessage('The link is invalid or has expired.', Alert::Danger),
		};

		if ($result === ConfirmResult::Invalid && $this->verification->isEnabled()) {
			$this->redirect('verification');
		}

		if (!$this->getUser()->isLoggedIn()) {
			$this->redirect('in');
		}

		$this->redirect($this->signedInDestination());
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
		$this->template->verificationEnabled = $this->verification->isEnabled();
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
			$this->flashMessage($this->verification->isEnabled()
				? 'Your account has been created. Confirm your email address using the link we have sent you, then sign in.'
				: 'Your registration has been successfully completed, you can now log in.', Alert::Success);
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


	/**
	 * Sends a new confirmation link; the answer is the same whether the address exists or not.
	 * @throws Throwable
	 */
	protected function createComponentSignVerification(): Form
	{
		$form = $this->factory->create();
		$form->addEmailField();
		$form->addSubmit('send', 'Send a new link');
		$form->onSuccess[] = function (Form $form, array $values): void {
			$ip = $this->getHttpRequest()->getRemoteAddress() ?? 'unknown';
			if (!$this->verification->resend((string) $values['email'], $ip, $this->lang)) {
				$form->addError('Too many requests. Try again later.');
				return;
			}

			$this->flashMessage('If the address is registered and not yet confirmed, we have sent a new link to it.', Alert::Success);
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
		} catch (AuthenticationException $e) {
			// The password was right, so this is not a failed attempt.
			if ($e->getCode() === Authenticator::NotApproved) {
				$form->addError('Please confirm your email address first. You can request a new confirmation link below.');
				// Shown only now: the password was right, so the link tells nothing to someone who does not know it.
				$this->template->notConfirmed = true;
				return;
			}

			$this->signInLimiter->registerFailure($values->email, $ip);
			$form->addError('The email or password is incorrect.');
			return;
		}

		$this->signInLimiter->registerSuccess($values->email);
		$this->restoreRequest($this->backlink);
		$this->redirect($this->signedInDestination());
	}


	/**
	 * SignedInDestination when the user may open it, otherwise FallbackDestination. Checked like the Authorization
	 * trait of drago-ex/permission does ("{action}-read" of the presenter); without an authorizator all is allowed.
	 */
	private function signedInDestination(): string
	{
		$user = $this->getUser();
		if ($user->getAuthorizatorIfExists() === null) {
			return self::SignedInDestination;
		}

		// ':Backend:Admin:' -> resource 'Backend:Admin', privilege 'default-read'
		$destination = ltrim(self::SignedInDestination, ':');
		$separator = (int) strrpos($destination, ':');
		$resource = substr($destination, 0, $separator);
		$action = substr($destination, $separator + 1) ?: 'default';

		try {
			$allowed = $user->isAllowed($resource, "$action-read");
		} catch (InvalidStateException) {
			// a resource the ACL does not know is denied
			$allowed = false;
		}

		return $allowed ? self::SignedInDestination : self::FallbackDestination;
	}
}
