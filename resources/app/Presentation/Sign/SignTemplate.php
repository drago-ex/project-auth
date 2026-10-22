<?php

declare(strict_types=1);

namespace App\Presentation\Sign;

use App\Presentation\BaseTemplate;
use App\Presentation\Sign\Recovery\Token;


final class SignTemplate extends BaseTemplate
{
	public Token $signRecoveryToken;
	public bool $verificationEnabled;

	/** The right password of an account that is not confirmed yet was entered, so the resend link is shown. */
	public bool $notConfirmed = false;
}
