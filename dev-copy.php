#!/usr/bin/env php
<?php

declare(strict_types=1);

use Composer\InstalledVersions;

// Try to find composer's autoload
$autoloadPaths = [
	getcwd() . '/vendor/autoload.php',
	__DIR__ . '/../../../autoload.php',
	__DIR__ . '/../vendor/autoload.php',
];

$autoloadFound = false;
foreach ($autoloadPaths as $path) {
	if (file_exists($path)) {
		require $path;
		$autoloadFound = true;
		break;
	}
}

if (!$autoloadFound) {
	die("❌ Error: composer autoload.php not found. Run 'composer install' first.\n");
}

$projectRoot = getcwd();

/**
 * PARAMETRY:
 * --verbose / -v  : vypíše detailní info o kopírování
 * --dev / --root  : vynutí instalaci i z root balíčku (užitečné při vývoji balíčku)
 */
$verbose = in_array('--verbose', $argv) || in_array('-v', $argv);
$installRoot = in_array('--dev', $argv) || in_array('--root', $argv);

$stats = ['copied' => 0, 'skipped' => 0, 'errors' => 0];

function message(string $msg, string $icon = '', bool $force = false): void
{
	global $verbose;
	if ($verbose || $force) {
		echo $icon . ' ' . $msg . "\n";
	}
}

function ensureDir(string $dir): void
{
	if (!is_dir($dir) && !mkdir($dir, 0o777, true) && !is_dir($dir)) {
		throw new RuntimeException("Cannot create directory: $dir");
	}
}

function copyFile(string $source, string $destination, bool $overwrite = false): void
{
	global $stats;
	ensureDir(dirname($destination));

	if (!$overwrite && file_exists($destination)) {
		message($destination, '⚠️ Skipped (exists):');
		$stats['skipped']++;
		return;
	}

	if (!copy($source, $destination)) {
		$stats['errors']++;
		throw new RuntimeException("Failed copying $source → $destination");
	}

	$stats['copied']++;
	$icon = $overwrite && file_exists($destination) ? '🔄 Replaced:' : '✅ Copied:';
	message("$source → $destination", $icon);
}

function recursiveCopy(string $source, string $destination, bool $overwrite = false): void
{
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($iterator as $item) {
		$targetPath = $destination . '/' . substr($item->getPathname(), strlen($source) + 1);
		if ($item->isDir()) {
			ensureDir($targetPath);
		} else {
			copyFile($item->getPathname(), $targetPath, $overwrite);
		}
	}
}

function installPackageResources(string $packagePath, string $projectRoot, bool $allowLibraryInstall): void
{
	global $stats;
	$composerPath = $packagePath . '/composer.json';
	if (!is_file($composerPath)) return;

	$composer = json_decode(file_get_contents($composerPath), true);
	$install = $composer['extra']['drago-project']['install'] ?? [];
	$sections = ['copy' => false, 'replace' => true];

	$type = $composer['type'] ?? 'library';
	if ($type === 'library' && !$allowLibraryInstall && (!empty($install['copy']) || !empty($install['replace']))) {
		return;
	}

	foreach ($sections as $sectionName => $overwrite) {
		$items = $install[$sectionName] ?? null;
		if (!$items) continue;

		foreach ($items as $srcRel => $dstRel) {
			$source = $packagePath . '/' . $srcRel;
			if (!file_exists($source)) {
				message($source, '❌ Source not found:');
				$stats['errors']++;
				continue;
			}

			$destination = $dstRel === '' ? $projectRoot . '/' . basename($source) : $projectRoot . '/' . $dstRel;
			if (is_file($source)) {
				if (is_dir($destination) || (!pathinfo($destination, PATHINFO_EXTENSION) && !file_exists($destination))) {
					ensureDir($destination);
					$destination .= '/' . basename($source);
				}
				copyFile($source, $destination, $overwrite);
			} elseif (is_dir($source)) {
				recursiveCopy($source, $destination, $overwrite);
			}
		}
	}
}

$rootComposerPath = $projectRoot . '/composer.json';
if (!file_exists($rootComposerPath)) die("❌ Error: composer.json not found in current directory.\n");
$rootComposer = json_decode(file_get_contents($rootComposerPath), true);
$allowLibraryInstall = $rootComposer['extra']['drago-project']['allow-library-install'] ?? false;

// Získání názvu aktuálního root balíčku
$rootPackageName = InstalledVersions::getRootPackage()['name'] ?? null;

foreach (InstalledVersions::getInstalledPackages() as $packageName) {
	// 1. Přeskočit samotný instalátor
	if ($packageName === 'drago-ex/project-installer') continue;
	
	// 2. Kontrola, zda se jedná o balíček v rootu, ve kterém právě jsme
	if ($packageName === $rootPackageName && !$installRoot) {
		if ($verbose) message("Skipping root package '$packageName' (use --dev to install its resources)", 'ℹ️');
		continue;
	}

	try {
		$packagePath = InstalledVersions::getInstallPath($packageName);
		if ($packagePath) {
			installPackageResources($packagePath, $projectRoot, $allowLibraryInstall);
		}
	} catch (Throwable) {
		continue;
	}
}

echo "✔️ Finished ({$stats['copied']} copied, {$stats['skipped']} skipped, {$stats['errors']} errors)\n";
