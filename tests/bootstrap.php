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

	// The package's own classes first, then those of its dependencies installed by drago-install --dev.
	$path = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
	foreach (['/../resources/app/', '/../.dev-resource/app/'] as $dir) {
		if (is_file($file = __DIR__ . $dir . $path)) {
			require $file;
			return;
		}
	}
});

Tester\Environment::setup();
Tester\Environment::setupFunctions();
