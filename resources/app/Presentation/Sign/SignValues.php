<?php

declare(strict_types=1);

namespace App\Presentation\Sign;

use Drago\Utils\ExtraArrayHash;


class SignValues extends ExtraArrayHash
{
	public const string
		Email = 'email',
		Password = 'password';

	public string $username;
	public string $email;
}
