<?php

declare(strict_types=1);

namespace App\Presentation\Sign;

use Drago\Form\ExtraForms;


/** @extends \Drago\Application\UI\Factory<ExtraForms> */
readonly class Factory extends \Drago\Application\UI\Factory
{
	protected function createForm(): ExtraForms
	{
		return new ExtraForms;
	}
}
