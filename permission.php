<?php

declare(strict_types=1);

/**
 * Basic access rules of this package for drago-ex/permission.
 * Generated into the project by: php vendor/bin/create-permission --packages
 */
return [
	// Guests may sign in, sign up and recover their password.
	[
		'className' => 'PermissionProvider',
		'namespace' => 'App\Presentation\Sign',
		'resource' => 'Sign',
		'outputDir' => 'app/Presentation/Sign',
		'allow' => ['RoleGuest,self::Resource'],
	],
];
