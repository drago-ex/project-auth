<?php declare(strict_types=1);

namespace App\Presentation\Sign\Verification;

use App\Model\User\UserEntity;
use App\Model\User\UserRepository;
use App\Model\User\UserTokenRepository;
use App\Presentation\Sign\Email\EmailSender;
use App\Presentation\Sign\RateLimiter;
use Dibi\Exception;
use Dibi\UniqueConstraintViolationException;
use Drago\Attr\AttributeDetectionException;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;


/**
 * Confirms email addresses by one-time links: after registration, and when a user changes the address.
 * With verification turned off, new accounts are verified at once and an email change applies immediately.
 */
class VerificationService
{
	public const int VerifyLifetime = 86400,
		EmailChangeLifetime = 3600;

	private const string ResendBucket = 'verification-resend',
		AccountExistsBucket = 'account-exists';

	private const int MaxResends = 3,
		MaxResendsPerIp = 10,
		MaxAccountExists = 3,
		ResendWindow = 3600;

	private const string EmailDir = __DIR__ . '/../Email';


	public function __construct(
		private readonly bool $enabled,
		private readonly UserRepository $userRepository,
		private readonly UserTokenRepository $tokenRepository,
		private readonly EmailSender $emailSender,
		private readonly LinkGenerator $linkGenerator,
		private readonly RateLimiter $limiter,
	) {
	}


	public function isEnabled(): bool
	{
		return $this->enabled;
	}


	/**
	 * Sends the confirmation link to a new account, or a welcome email when verification is turned off.
	 * @throws Exception
	 * @throws InvalidLinkException
	 */
	public function registered(int $userId, string $email, string $username, string $lang): void
	{
		if (!$this->enabled) {
			$link = $this->linkGenerator->link('Sign:in', ['lang' => $lang]);
			$this->emailSender->send($email, 'Your account has been created', self::EmailDir . '/sign-up.latte', [
				'username' => $username,
				'link' => $link,
			], $lang);
			return;
		}

		$this->sendVerification($userId, $email, $username, $lang);
	}


	/**
	 * Someone registered with an address that already has an account. The form answers as if the account was
	 * created, so it does not tell who is registered, and the owner gets an email with the way to sign in.
	 * Only with verification on: without it, the new account is usable at once, so the form says the address is taken.
	 * At most MaxAccountExists emails per address and hour (the sign-up limit is per IP), so the form cannot be used
	 * to flood an inbox; over the limit nothing is sent and the form answers the same.
	 * @throws InvalidLinkException
	 */
	public function alreadyRegistered(string $email, string $lang): void
	{
		if (!$this->limiter->isAllowed(self::AccountExistsBucket, $email, self::MaxAccountExists)) {
			return;
		}

		$this->limiter->hit(self::AccountExistsBucket, $email, self::ResendWindow);
		$this->emailSender->send($email, 'Someone tried to create an account', self::EmailDir . '/account-exists.latte', [
			'link' => $this->linkGenerator->link('Sign:recovery', ['lang' => $lang]),
		], $lang);
	}


	/**
	 * Sends a new confirmation link. The answer does not depend on whether the address exists,
	 * so it cannot be used to find out who has an account.
	 * @return bool false when too many links were requested
	 * @throws Exception
	 * @throws AttributeDetectionException
	 * @throws InvalidLinkException
	 */
	public function resend(string $email, string $ip, string $lang): bool
	{
		if (
			!$this->limiter->isAllowed(self::ResendBucket, $email, self::MaxResends)
			|| !$this->limiter->isAllowed(self::ResendBucket . '-ip', $ip, self::MaxResendsPerIp)
		) {
			return false;
		}

		$this->limiter->hit(self::ResendBucket, $email, self::ResendWindow);
		$this->limiter->hit(self::ResendBucket . '-ip', $ip, self::ResendWindow);

		$user = $this->userRepository->findUserByEmail($email);
		if ($this->enabled && $user !== null && $user->verifiedAt === null) {
			$this->sendVerification($user->id, $user->email, $user->username, $lang);
		}

		return true;
	}


	/**
	 * Changes the email address of the user. With verification on, the change waits for the link sent
	 * to the new address; otherwise it applies at once. The previous address is notified either way.
	 * @return bool true when the change waits for confirmation
	 * @throws Exception
	 * @throws AttributeDetectionException
	 * @throws InvalidLinkException
	 */
	public function changeEmail(UserEntity $user, string $newEmail, string $lang): bool
	{
		if (!$this->enabled) {
			$this->applyEmailChange($user, $newEmail, $lang);
			return false;
		}

		$token = $this->tokenRepository->create($user->id, UserTokenRepository::TypeEmailChange, self::EmailChangeLifetime, $newEmail);
		$this->emailSender->send($newEmail, 'Confirm your new email address', self::EmailDir . '/email-change.latte', [
			'link' => $this->verifyLink($token, $lang),
		], $lang);

		return true;
	}


	/**
	 * Applies a link from an email.
	 * @throws Exception
	 * @throws AttributeDetectionException
	 * @throws InvalidLinkException
	 */
	public function confirm(string $token, string $lang): ConfirmResult
	{
		if ($token === '') {
			return ConfirmResult::Invalid;
		}

		$record = $this->tokenRepository->consume($token, UserTokenRepository::TypeVerify);
		if ($record !== null) {
			$user = $this->findUser($record['user_id']);
			if ($user === null) {
				return ConfirmResult::Invalid;
			}

			$user->verifiedAt ??= new \DateTimeImmutable;
			$this->userRepository->save($user);
			return ConfirmResult::Verified;
		}

		$record = $this->tokenRepository->consume($token, UserTokenRepository::TypeEmailChange);
		$user = $record !== null ? $this->findUser($record['user_id']) : null;
		if ($record === null || $record['email'] === null || $user === null) {
			return ConfirmResult::Invalid;
		}

		$owner = $this->userRepository->findUserByEmail($record['email']);
		if ($owner !== null && $owner->id !== $user->id) {
			return ConfirmResult::EmailTaken;
		}

		try {
			$this->applyEmailChange($user, $record['email'], $lang);
		} catch (UniqueConstraintViolationException) {
			return ConfirmResult::EmailTaken;
		}

		return ConfirmResult::EmailChanged;
	}


	/**
	 * @throws Exception
	 * @throws InvalidLinkException
	 */
	private function sendVerification(int $userId, string $email, string $username, string $lang): void
	{
		$token = $this->tokenRepository->create($userId, UserTokenRepository::TypeVerify, self::VerifyLifetime);
		$this->emailSender->send($email, 'Confirm your email address', self::EmailDir . '/verify.latte', [
			'username' => $username,
			'link' => $this->verifyLink($token, $lang),
		], $lang);
	}


	/**
	 * @throws Exception
	 * @throws AttributeDetectionException
	 * @throws InvalidLinkException
	 */
	private function applyEmailChange(UserEntity $user, string $newEmail, string $lang): void
	{
		$previousEmail = $user->email;
		$user->email = $newEmail;
		$user->verifiedAt = new \DateTimeImmutable;
		$this->userRepository->save($user);

		$this->emailSender->send($previousEmail, 'Your email address has been changed', self::EmailDir . '/email-changed.latte', [
			'newEmail' => $newEmail,
			'link' => $this->linkGenerator->link('Sign:recovery', ['lang' => $lang]),
		], $lang);
	}


	/**
	 * @throws InvalidLinkException
	 */
	private function verifyLink(string $token, string $lang): string
	{
		return $this->linkGenerator->link('Sign:verify', ['token' => $token, 'lang' => $lang]);
	}


	/**
	 * @throws Exception
	 * @throws AttributeDetectionException
	 */
	private function findUser(int $id): ?UserEntity
	{
		return $this->userRepository->get($id)->record();
	}
}
