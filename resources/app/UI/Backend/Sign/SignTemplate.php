<?php

declare(strict_types=1);

namespace App\UI\Backend\Sign;

use App\Core\User\UserAccess;
use App\UI\BaseTemplate;
use Nette\Security\User;


final class SignTemplate extends BaseTemplate
{
	/**
	 * The logged-in user.
	 * This can be either the Nette User or a custom User class from App\Core\User.
	 */
	public UserAccess|User $user;
	public SignRecoveryToken $signRecoveryToken;
}
