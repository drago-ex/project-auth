<?php

declare(strict_types=1);

use App\Presentation\Accessory\RequireLogged;


/** Lets PHPStan analyse the RequireLogged trait, which no class in the package uses. */
class TraitStub
{
	use RequireLogged;
}
