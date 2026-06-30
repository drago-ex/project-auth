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


	public function __construct(
		private Session $session,
	) {
	}


	private function getSection(): SessionSection
	{
		return $this->session->getSection('recovery');
	}


	/**
	 * @throws RandomException
	 */
	public function generateToken(string $email): string
	{
		$section = $this->getSection()
			->setExpiration('15 minutes');

		$section->remove('tokenCheck');
		$token = Random::generate(self::TokenLength, '0-9');
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
			->remove(['token', 'tokenCheck', 'email', 'attempts']);
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
}
