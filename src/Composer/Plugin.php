<?php

declare(strict_types=1);

namespace DragoAuthPlugin\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use DragoAuthPlugin\Installer;


final class Plugin implements PluginInterface, EventSubscriberInterface
{
	public static function getSubscribedEvents(): array
	{
		return [
			'post-package-install' => 'onPackageInstall',
			'post-package-update'  => 'onPackageInstall',
		];
	}


	public function activate(Composer $composer, IOInterface $io)
	{
		// TODO: Implement activate() method.
	}


	public function deactivate(Composer $composer, IOInterface $io)
	{
		// TODO: Implement deactivate() method.
	}


	public function uninstall(Composer $composer, IOInterface $io)
	{
		// TODO: Implement uninstall() method.
	}


	public function onPackageInstall(PackageEvent $event): void
	{
		$operation = $event->getOperation();
		$package = method_exists($operation, 'getPackage') ? $operation->getPackage() : null;
		if ($package && $package->getName() === 'drago-ex/project-auth') {
			Installer::install();
		}
	}
}
