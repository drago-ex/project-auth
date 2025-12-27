<?php

declare(strict_types=1);

namespace DragoAuthPlugin;


final class Installer
{
	public static function install(): void
	{
		$root = self::getProjectRoot();
		$projectRoot = $root . '/app';
		$files = [
			'UI/Backend/Admin/AdminPresenter.php',
			'UI/Backend/Admin/AdminTemplate.php',
			'UI/Backend/Admin/default.latte',
			'UI/Backend/Sign/@layout.latte',
			'UI/Backend/Sign/in.latte',
			'UI/Backend/Sign/out.latte',
			'UI/Backend/Sign/recovery.email.latte',
			'UI/Backend/Sign/recovery.latte',
			'UI/Backend/Sign/SignData.php',
			'UI/Backend/Sign/SignFactory.php',
			'UI/Backend/Sign/SignForm.php',
			'UI/Backend/Sign/SignPresenter.php',
			'UI/Backend/Sign/SignRecoveryEmail.php',
			'UI/Backend/Sign/SignRecoveryFactory.php',
			'UI/Backend/Sign/SignRecoverySession.php',
			'UI/Backend/Sign/SignRecoveryToken.php',
			'UI/Backend/Sign/SignTemplate.php',
			'UI/Backend/Sign/SignUpData.php',
			'UI/Backend/Sign/SignUpFactory.php',
			'UI/Backend/Sign/SignUserRepository.php',
			'UI/Backend/Sign/up.latte',
			'UI/Backend/@layout.latte',
			'UI/Backend/conf.neon',
			'UI/Backend/Router.php',
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
