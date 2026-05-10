<?php

declare(strict_types=1);

namespace App\UI;

use Nette\Application\UI\Presenter;

abstract class BasePresenter extends Presenter
{
	public function getTranslator(): mixed
	{
		return null;
	}
}
