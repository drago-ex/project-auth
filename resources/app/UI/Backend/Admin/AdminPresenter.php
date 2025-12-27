<?php

declare(strict_types=1);

namespace App\UI\Backend\Admin;

use App\Core\User\UserRequireLogged;
use App\UI\BasePresenter;


/**
 * AdminPresenter is responsible for handling the admin section of the application.
 * It extends the base Presenter class and is linked with the AdminTemplate for rendering.
 *
 * @property-read AdminTemplate $template
 */
final class AdminPresenter extend BasePresenter
{
	use UserRequireLogged;
}
