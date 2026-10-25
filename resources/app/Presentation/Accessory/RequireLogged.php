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
			}

			// After signing in, the user gets back to the page they asked for. Only a plain GET page is stored: a signal
			// or a form replayed after signing in would pass as a same-origin request (e.g. a delete from a sent link).
			$request = $presenter->getRequest();
			$backlink = $presenter->getSignal() === null && $request->isMethod('GET') ? $presenter->storeRequest() : null;
			$presenter->redirect(':Sign:in', ['backlink' => $backlink]);
		};
	}
}
