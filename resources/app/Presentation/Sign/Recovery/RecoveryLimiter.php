<?php

declare(strict_types=1);

namespace App\Presentation\Sign\Recovery;

use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Utils\Strings;


/**
 * Per-email limits for the password recovery flow.
 * Unlike the session counters they cannot be reset by opening a new session.
 */
readonly class RecoveryLimiter
{
	private const int
		MaxRequests = 5,
		RequestWindow = 3600,
		MaxFailures = 10,
		FailureWindow = 900;

	private Cache $cache;


	public function __construct(Storage $storage)
	{
		$this->cache = new Cache($storage, self::class);
	}


	public function isRequestAllowed(string $email): bool
	{
		return $this->count('request', $email) < self::MaxRequests;
	}


	public function registerRequest(string $email): void
	{
		$this->hit('request', $email, self::RequestWindow);
	}


	public function isVerifyAllowed(string $email): bool
	{
		return $this->count('failure', $email) < self::MaxFailures;
	}


	public function registerFailure(string $email): void
	{
		$this->hit('failure', $email, self::FailureWindow);
	}


	private function key(string $type, string $email): string
	{
		return $type . ':' . hash('sha256', Strings::lower(trim($email)));
	}


	private function count(string $type, string $email): int
	{
		$data = $this->cache->load($this->key($type, $email));
		return is_array($data) && $data['until'] > time()
			? (int) $data['count']
			: 0;
	}


	/**
	 * Fixed window: the window starts with the first hit and is not extended by later ones.
	 */
	private function hit(string $type, string $email, int $window): void
	{
		$key = $this->key($type, $email);
		$now = time();
		$data = $this->cache->load($key);

		if (!is_array($data) || $data['until'] <= $now) {
			$data = ['count' => 0, 'until' => $now + $window];
		}

		$data['count']++;
		$this->cache->save($key, $data, [Cache::Expire => $data['until'] - $now]);
	}
}
