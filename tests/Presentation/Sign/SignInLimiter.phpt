<?php

/**
 * Test: App\Presentation\Sign\SignInLimiter
 */

declare(strict_types=1);

use App\Presentation\Sign\RateLimiter;
use App\Presentation\Sign\SignInLimiter;
use Nette\Caching\Storages\MemoryStorage;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


test('Failed sign-ins are limited per normalized email address', function () {
	$limiter = new SignInLimiter(new RateLimiter(new MemoryStorage));

	for ($failure = 0; $failure < 10; $failure++) {
		Assert::true($limiter->isAllowed(' User@Example.com ', '192.0.2.' . $failure));
		$limiter->registerFailure(' User@Example.com ', '192.0.2.' . $failure);
	}

	Assert::false($limiter->isAllowed('user@example.com', '198.51.100.1'));
	Assert::true($limiter->isAllowed('other@example.com', '198.51.100.1'));
});


test('Failed sign-ins are limited per IP address across email addresses', function () {
	$limiter = new SignInLimiter(new RateLimiter(new MemoryStorage));

	for ($failure = 0; $failure < 30; $failure++) {
		Assert::true($limiter->isAllowed("user$failure@example.com", '192.0.2.1'));
		$limiter->registerFailure("user$failure@example.com", '192.0.2.1');
	}

	Assert::false($limiter->isAllowed('new@example.com', '192.0.2.1'));
	Assert::true($limiter->isAllowed('new@example.com', '192.0.2.2'));
});


test('A successful sign-in clears the failures of the email address', function () {
	$limiter = new SignInLimiter(new RateLimiter(new MemoryStorage));

	for ($failure = 0; $failure < 9; $failure++) {
		$limiter->registerFailure('user@example.com', '192.0.2.' . $failure);
	}

	$limiter->registerSuccess('User@Example.com');
	$limiter->registerFailure('user@example.com', '192.0.2.100');
	Assert::true($limiter->isAllowed('user@example.com', '192.0.2.101'));
});


test('Sign-in limits do not share buckets with other limiters', function () {
	$rateLimiter = new RateLimiter(new MemoryStorage);
	$limiter = new SignInLimiter($rateLimiter);

	$rateLimiter->hit('recovery-failure', 'user@example.com', 900);
	$rateLimiter->hit('recovery-ip', '192.0.2.1', 900);

	Assert::true($limiter->isAllowed('user@example.com', '192.0.2.1'));
});
