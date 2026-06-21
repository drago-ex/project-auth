<?php

declare(strict_types=1);

namespace App\Presentation\Accessory;

use Nette\Application\UI\Presenter;
use Nette\Security\User;


trait RequireLogged
{
	public function injectRequireLoggedUser(Presenter $presenter, User $user): void
	{
		$presenter->onStartup[] = function () use ($presenter, $user) {
			if ($user->isLoggedIn()) {
				return;
			}

			if ($user->getLogoutReason() === $user::LogoutInactivity) {
				$presenter->flashMessage('You have been signed out due to inactivity. Please sign in again.');
				$presenter->redirect(':Sign:in', ['backlink' => $presenter->storeRequest()]);
			} else {
				$presenter->redirect(':Sign:in');
			}
		};
	}
}
