<?php

declare(strict_types=1);

namespace App\UI\Sign;

use Nette;
use Nette\Application\Routers\RouteList;


/** Sign router. */
final class Router
{
	use Nette\StaticClass;

	/** Create router. */
	public static function create(): RouteList
	{
		$router = new RouteList;
		$router->withModule('Sign')
			->addRoute('[<lang=cs cs|en>/]<presenter>/<action>', 'Sign:default');

		return $router;
	}
}
