<?php

declare(strict_types=1);

namespace App\Presentation\Sign;


/**
 * Limits failed sign-in attempts per email and per IP address.
 * The per-IP limit is higher, so users behind a shared address are not locked out by each other.
 */
readonly class SignInLimiter
{
	private const int
		MaxFailures = 10,
		MaxFailuresPerIp = 30,
		FailureWindow = 900;


	public function __construct(
		private RateLimiter $limiter,
	) {
	}


	public function isAllowed(string $email, string $ip): bool
	{
		return $this->limiter->isAllowed('signin-failure', $email, self::MaxFailures)
			&& $this->limiter->isAllowed('signin-ip', $ip, self::MaxFailuresPerIp);
	}


	public function registerFailure(string $email, string $ip): void
	{
		$this->limiter->hit('signin-failure', $email, self::FailureWindow);
		$this->limiter->hit('signin-ip', $ip, self::FailureWindow);
	}


	public function registerSuccess(string $email): void
	{
		$this->limiter->clear('signin-failure', $email);
	}
}
