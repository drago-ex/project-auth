<?php

/**
 * Test: App\Presentation\Sign\Recovery\RecoveryLimiter
 */

declare(strict_types=1);

use App\Presentation\Sign\RateLimiter;
use App\Presentation\Sign\Recovery\RecoveryLimiter;
use Nette\Caching\Storages\MemoryStorage;
use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';


test('Recovery requests are limited across normalized email addresses', function () {
	$limiter = new RecoveryLimiter(new RateLimiter(new MemoryStorage));

	for ($request = 0; $request < 5; $request++) {
		Assert::true($limiter->isRequestAllowed(' User@Example.com '));
		$limiter->registerRequest(' User@Example.com ');
	}

	Assert::false($limiter->isRequestAllowed('user@example.com'));
});


test('Verification failures are limited separately from recovery requests', function () {
	$limiter = new RecoveryLimiter(new RateLimiter(new MemoryStorage));

	for ($failure = 0; $failure < 10; $failure++) {
		Assert::true($limiter->isVerifyAllowed('user@example.com'));
		$limiter->registerFailure('user@example.com');
	}

	Assert::false($limiter->isVerifyAllowed('user@example.com'));
	Assert::true($limiter->isRequestAllowed('user@example.com'));
});


test('Recovery requests are limited per IP separately from email addresses', function () {
	$limiter = new RecoveryLimiter(new RateLimiter(new MemoryStorage));

	for ($request = 0; $request < 20; $request++) {
		Assert::true($limiter->isIpAllowed('192.0.2.1'));
		$limiter->registerIp('192.0.2.1');
	}

	Assert::false($limiter->isIpAllowed('192.0.2.1'));
	Assert::true($limiter->isIpAllowed('192.0.2.2'));
	Assert::true($limiter->isRequestAllowed('user@example.com'));
});


test('Rate limit buckets are isolated and subjects are normalized', function () {
	$rateLimiter = new RateLimiter(new MemoryStorage);

	$rateLimiter->hit('signup', ' User@Example.com ', 3600);

	Assert::false($rateLimiter->isAllowed('signup', 'user@example.com', 1));
	Assert::true($rateLimiter->isAllowed('recovery-request', 'user@example.com', 1));
});
