<?php

declare(strict_types=1);

namespace DragoAuthPlugin;


final class Installer
{
	public static function install(): void
	{
		$root = self::getProjectRoot();
		$projectRoot = $root . '/app/UI/Backend';
		$files = [
			'Admin/AdminPresenter.php',
			'Admin/AdminTemplate.php',
			'Admin/default.latte',
			'Sign/@layout.latte',
			'Sign/in.latte',
			'Sign/out.latte',
			'Sign/recovery.email.latte',
			'Sign/recovery.latte',
			'Sign/SignData.php',
			'Sign/SignFactory.php',
			'Sign/SignForm.php',
			'Sign/SignPresenter.php',
			'Sign/SignRecoveryEmail.php',
			'Sign/SignRecoveryFactory.php',
			'Sign/SignRecoverySession.php',
			'Sign/SignRecoveryToken.php',
			'Sign/SignTemplate.php',
			'Sign/SignUpData.php',
			'Sign/SignUpFactory.php',
			'Sign/SignUserRepository.php',
			'Sign/up.latte',
			'@layout.latte',
			'conf.neon',
			'Router.php',
		];

		foreach ($files as $file) {
			self::copy(__DIR__ . '/../resources/app/' . $file, $projectRoot . '/' . $file);
		}

		echo "[project-auth] Auth module support installed\n";
	}


	private static function getProjectRoot(): string
	{
		// vendor/drago-ex/project-auth/src → ROOT
		return dirname(__DIR__, 4);
	}


	private static function copy(string $from, string $to): void
	{
		if (file_exists($to)) {
			echo "[project-auth] Skipped (exists): $to\n";
			return;
		}

		@mkdir(dirname($to), 0o777, true);
		copy($from, $to);
	}
}
