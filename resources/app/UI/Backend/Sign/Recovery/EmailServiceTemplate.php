<?php

declare(strict_types=1);

namespace App\UI\Backend\Sign\Recovery;


use Nette\Bridges\ApplicationLatte\Template;


class EmailServiceTemplate extends Template
{
	public ?string $token = null;
}
