<?php

declare(strict_types=1);

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install dependencies using `composer install`';
	exit(1);
}

spl_autoload_register(function (string $class): void {
	$prefix = 'App\\';
	if (!str_starts_with($class, $prefix)) {
		return;
	}

	$file = __DIR__ . '/../resources/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
	if (is_file($file)) {
		require $file;
	}
});

Tester\Environment::setup();
Tester\Environment::setupFunctions();
