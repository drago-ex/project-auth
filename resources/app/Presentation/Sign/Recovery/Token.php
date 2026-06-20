<?php

declare(strict_types=1);

namespace App\Presentation\Sign\Recovery;


class Token
{
	public function __construct(
		public bool $hasToken = false,
		public bool $isTokenChecked = false,
	) {
	}
}
