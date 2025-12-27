<?php

declare(strict_types=1);

namespace App\UI\Backend\Sign;

use App\Core\User\User;
use App\UI\BaseTemplate;


final class SignTemplate extends BaseTemplate
{
	/**
	 * The logged-in user.
	 * This can be either the Nette User or a custom User class from App\Core\User.
	 */
	public \Nette\Security\User|User $user;
	public SignRecoveryToken $signRecoveryToken;
}
