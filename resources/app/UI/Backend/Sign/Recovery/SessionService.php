<?php

declare(strict_types=1);

namespace App\UI\Backend\Sign\Recovery;

use Nette\Http\Session;
use Nette\Http\SessionSection;
use Nette\Utils\Random;
use RuntimeException;


/**
 * Session handler for managing password recovery tokens.
 * Handles the creation, validation, and removal of recovery tokens within the session.
 */
readonly class SessionService
{
	public function __construct(
		private Session $session,
	) {
	}


	private function getSection(): SessionSection
	{
		return $this->session
			->getSection('recovery')
			->setExpiration('30 minutes');
	}


	/**
	 * Sets a new token and email for password recovery in the session.
	 * Generates a random 6-character token and stores it along with the provided email.
	 */
	public function generateToken(string $email): void
	{
		$section = $this->getSection();
		$token = Random::generate(6);
		$section->set('token', $token);
		$section->set('email', $email);
	}


	/**
	 * Retrieves the stored email address for password recovery from the session.
	 * This method guarantees to PHPStan that the return value is always a string.
	 *
	 * @throws RuntimeException If the email is not found in the session (e.g. session expired).
	 */
	public function getEmail(): string
	{
		$email = $this->getSection()->get('email');
		if ($email === null) {
			throw new RuntimeException('Password recovery session has expired.');
		}
		return $email;
	}


	/**
	 * Retrieves the stored password recovery token from the session.
	 */
	public function getToken(): ?string
	{
		return $this->getSection()
			->get('token');
	}


	/**
	 * Marks the token as checked in the session.
	 */
	public function setTokenCheck(): void
	{
		$this->getSection()
			->set('tokenCheck', true);
	}


	/**
	 * Removes the password recovery token and token check from the session.
	 */
	public function removeToken(): void
	{
		$this->getSection()
			->remove(['token', 'tokenCheck', 'email']);
	}


	/**
	 * Validates if the provided token matches the stored token in the session.
	 */
	public function isTokenValid(string $token): bool
	{
		$section = $this->getSection();

		// If tokenCheck is true, the token must not be reused.
		if ($section->get('tokenCheck') === true) {
			return false;
		}

		return $section->get('token') === $token;
	}


	/**
	 * Creates a SignRecoveryToken object based on the current session data.
	 */
	public function createSignRecoveryToken(): Token
	{
		return new Token(
			hasToken: $this->getToken() !== null,
			isTokenChecked: (bool) $this->getSection()->get('tokenCheck'),
		);
	}
}
