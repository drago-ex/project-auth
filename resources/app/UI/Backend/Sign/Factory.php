<?php

declare(strict_types=1);

namespace App\UI\Backend\Sign;

use Nette\Localization\Translator;
use Nette\Security\User;


/** Factory for creating sign in form. */
readonly class Factory
{
	public function __construct(
		private Translator $translator,
		private User $user,
	) {
	}


	public function create(): Form
	{
		$form = new Form;
		if ($this->user->isLoggedIn()) {
			$form->addProtection();
		}

		$form->setTranslator($this->translator);
		return $form;
	}
}
