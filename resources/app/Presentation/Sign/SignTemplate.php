<?php

declare(strict_types=1);

namespace App\Presentation\Sign;

use App\Presentation\BaseTemplate;
use App\Presentation\Sign\Recovery\Token;


final class SignTemplate extends BaseTemplate
{
	public Token $signRecoveryToken;
}
