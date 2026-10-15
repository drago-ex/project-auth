<?php

/**
 * Test: App\Presentation\Sign\Recovery\RecoveryLimiter
 */

declare(strict_types=1);

use App\Presentation\Sign\Recovery\RecoveryLimiter;
use App\Presentation\Sign\RateLimiter;
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
