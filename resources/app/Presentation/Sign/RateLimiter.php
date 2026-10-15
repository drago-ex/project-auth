<?php

declare(strict_types=1);

namespace App\Presentation\Sign;

use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Utils\Strings;


/**
 * Fixed-window counters kept in the cache, independent of the user's session.
 * The subject (email, IP address) is hashed, so no personal data lands in the cache keys.
 */
readonly class RateLimiter
{
	private const string CacheNamespace = 'drago.auth.rateLimiter';

	private Cache $cache;


	public function __construct(Storage $storage)
	{
		$this->cache = new Cache($storage, self::CacheNamespace);
	}


	public function isAllowed(string $bucket, string $subject, int $max): bool
	{
		return $this->count($bucket, $subject) < $max;
	}


	/**
	 * The window starts with the first hit and is not extended by later ones.
	 */
	public function hit(string $bucket, string $subject, int $window): void
	{
		$key = $this->key($bucket, $subject);
		$now = time();
		$data = $this->cache->load($key);

		if (!is_array($data) || $data['until'] <= $now) {
			$data = ['count' => 0, 'until' => $now + $window];
		}

		$data['count']++;
		$this->cache->save($key, $data, [Cache::Expire => $data['until'] - $now]);
	}


	private function count(string $bucket, string $subject): int
	{
		$data = $this->cache->load($this->key($bucket, $subject));
		return is_array($data) && $data['until'] > time()
			? (int) $data['count']
			: 0;
	}


	private function key(string $bucket, string $subject): string
	{
		return $bucket . ':' . hash('sha256', Strings::lower(trim($subject)));
	}
}
