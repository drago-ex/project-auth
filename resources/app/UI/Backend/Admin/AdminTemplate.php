<?php

declare(strict_types=1);

namespace App\UI\Backend\Admin;

use App\Core\User\UserAccess;
use App\UI\BaseTemplate;


/**
 * AdminTemplate is used for rendering the admin section of the application.
 * It extends the base Template class to provide specific template functionality
 * for the admin section of the app.
 */
class AdminTemplate extends BaseTemplate
{
	public UserAccess $userAccess;
}
