<?php

declare(strict_types=1);

namespace App\UI\Backend\Sign;

use Drago\Utils\ExtraArrayHash;


class SignValues extends ExtraArrayHash
{
	public const string
		Email = 'email',
		Password = 'password';

	public string $email;
	public string $password;
}
