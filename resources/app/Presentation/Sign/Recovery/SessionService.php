<?php

declare(strict_types=1);

namespace App\Presentation\Sign\Recovery;

use Nette\Http\Session;
use Nette\Http\SessionSection;
use Nette\Utils\Random;
use Random\RandomException;
use RuntimeException;


readonly class SessionService
{
	private const int TokenLength = 6;
	private const int MaxAttempts = 5;
	private const int ResendCooldown = 60;


	public function __construct(
		private Session $session,
	) {
	}


	/**
	 * Generates a new recovery code. The failed-attempt counter is kept across
	 * resends, otherwise requesting a new code would reset the brute-force limit.
	 * @throws RandomException
	 * @throws RecoveryThrottledException
	 */
	public function generateToken(string $email): string
	{
		$section = $this->getSection();

		if ((int) $section->get('attempts') >= self::MaxAttempts) {
			throw new RecoveryThrottledException('Too many incorrect attempts. Try again later.');
		}

		$sentAt = (int) $section->get('sentAt');
		if ($sentAt > 0 && time() - $sentAt < self::ResendCooldown) {
			throw new RecoveryThrottledException('Please wait a moment before requesting a new code.');
		}

		$section->setExpiration('15 minutes');
		$section->remove('tokenCheck');
		$token = Random::generate(self::TokenLength, '0-9');
		$section->set('token', hash('sha256', $token));
		$section->set('email', $email);
		$section->set('sentAt', time());
		return $token;
	}


	/**
	 * Retrieves the stored email address for password recovery from the session.
	 * @throws RuntimeException
	 */
	public function getEmail(): string
	{
		$email = $this->getSection()->get('email');
		if ($email === null) {
			throw new RuntimeException('Password recovery session has expired.');
		}
		return $email;
	}


	public function getToken(): ?string
	{
		return $this->getSection()
			->get('token');
	}


	public function setTokenCheck(): void
	{
		$this->getSection()
			->set('tokenCheck', true);
	}


	public function removeToken(): void
	{
		$this->getSection()
			->remove(['token', 'tokenCheck', 'email', 'attempts', 'sentAt']);
	}


	public function isTokenValid(string $token): bool
	{
		$section = $this->getSection();
		$storedToken = $section->get('token');
		$attempts = (int) $section->get('attempts');

		if ($storedToken === null || $attempts >= self::MaxAttempts) {
			return false;
		}

		if ($section->get('tokenCheck') === true) {
			return false;
		}

		$isValid = hash_equals((string) $storedToken, hash('sha256', $token));
		if (!$isValid) {
			$section->set('attempts', $attempts + 1);
		}

		return $isValid;
	}


	/**
	 * True only if a code was issued and then successfully verified in this session.
	 */
	public function isTokenChecked(): bool
	{
		$section = $this->getSection();
		return $section->get('token') !== null
			&& $section->get('tokenCheck') === true;
	}


	public function hasAttemptsRemaining(): bool
	{
		return (int) $this->getSection()->get('attempts') < self::MaxAttempts;
	}


	public function createSignRecoveryToken(): Token
	{
		return new Token(
			hasToken: $this->getToken() !== null,
			isTokenChecked: (bool) $this->getSection()->get('tokenCheck'),
		);
	}


	private function getSection(): SessionSection
	{
		return $this->session->getSection('recovery');
	}
}
