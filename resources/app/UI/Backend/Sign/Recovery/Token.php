<?php

declare(strict_types=1);

namespace App\UI\Backend\Sign\Recovery;


class Token
{
	public function __construct(
		public bool $hasToken = false,
		public bool $isTokenChecked = false,
	) {
	}
}
