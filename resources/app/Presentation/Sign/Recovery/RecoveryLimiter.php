<?php

declare(strict_types=1);

namespace App\Presentation\Sign\Recovery;

use App\Presentation\Sign\RateLimiter;


/**
 * Limits of the password recovery flow per email and per IP address.
 * Unlike the session counters they cannot be reset by opening a new session.
 */
readonly class RecoveryLimiter
{
	private const int
		MaxRequests = 5,
		RequestWindow = 3600,
		MaxFailures = 10,
		FailureWindow = 900,
		MaxRequestsPerIp = 20;


	public function __construct(
		private RateLimiter $limiter,
	) {
	}


	public function isRequestAllowed(string $email): bool
	{
		return $this->limiter->isAllowed('recovery-request', $email, self::MaxRequests);
	}


	public function registerRequest(string $email): void
	{
		$this->limiter->hit('recovery-request', $email, self::RequestWindow);
	}


	public function isVerifyAllowed(string $email): bool
	{
		return $this->limiter->isAllowed('recovery-failure', $email, self::MaxFailures);
	}


	public function registerFailure(string $email): void
	{
		$this->limiter->hit('recovery-failure', $email, self::FailureWindow);
	}


	public function isIpAllowed(string $ip): bool
	{
		return $this->limiter->isAllowed('recovery-ip', $ip, self::MaxRequestsPerIp);
	}


	public function registerIp(string $ip): void
	{
		$this->limiter->hit('recovery-ip', $ip, self::RequestWindow);
	}
}
