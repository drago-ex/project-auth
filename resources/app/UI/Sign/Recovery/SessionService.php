<?php

declare(strict_types=1);

namespace App\UI\Sign\Recovery;

use Nette\Http\Session;
use Nette\Http\SessionSection;
use RuntimeException;


/** Session handler for managing password recovery tokens. */
readonly class SessionService
{
	private const int TokenLength = 6;
	private const int MaxAttempts = 5;


	public function __construct(
		private Session $session,
	) {
	}


	private function getSection(): SessionSection
	{
		return $this->session
			->getSection('recovery')
			->setExpiration('15 minutes');
	}


	/** Sets a new token and email for password recovery in the session. */
	public function generateToken(string $email): string
	{
		$section = $this->getSection();
		$token = str_pad((string) random_int(0, 999_999), self::TokenLength, '0', STR_PAD_LEFT);
		$section->set('token', hash('sha256', $token));
		$section->set('email', $email);
		$section->set('attempts', 0);
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


	/** Retrieves the stored password recovery token from the session. */
	public function getToken(): ?string
	{
		return $this->getSection()
			->get('token');
	}


	/** Marks the token as checked in the session. */
	public function setTokenCheck(): void
	{
		$this->getSection()
			->set('tokenCheck', true);
	}


	/** Removes the password recovery token and token check from the session. */
	public function removeToken(): void
	{
		$this->getSection()
			->remove(['token', 'tokenCheck', 'email', 'attempts']);
	}


	/** Validates if the provided token matches the stored token in the session. */
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


	/** Whether another token validation attempt is allowed. */
	public function hasAttemptsRemaining(): bool
	{
		return (int) $this->getSection()->get('attempts') < self::MaxAttempts;
	}


	/** Creates a SignRecoveryToken object based on the current session data. */
	public function createSignRecoveryToken(): Token
	{
		return new Token(
			hasToken: $this->getToken() !== null,
			isTokenChecked: (bool) $this->getSection()->get('tokenCheck'),
		);
	}
}
