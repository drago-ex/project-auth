<?php

declare(strict_types=1);

namespace App\UI\Sign;

use App\UI\BaseTemplate;
use App\UI\Sign\Recovery\Token;


/** Sign in template. */
final class SignTemplate extends BaseTemplate
{
	public Token $signRecoveryToken;
}
