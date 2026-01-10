<?php

declare(strict_types=1);

namespace App\UI\Backend\Sign;

use Drago\Utils\ExtraArrayHash;


class SignUpValues extends ExtraArrayHash
{
	public const string
		Username = 'username',
		Email = 'email',
		Password = 'password',
		Verify = 'verify';

	/** User's username */
	public string $username;

	/** User's email address */
	public string $email;

	/** User's password */
	public string $password;

	/** Password verification (for matching during registration) */
	public string $verify;

	/** Token for user verification or authentication */
	public string $token;
}
