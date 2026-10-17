<?php

declare(strict_types=1);

namespace App\Presentation\Sign\Email;

use Drago\Localization\Translator;
use Nette\Application\LinkGenerator;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Throwable;
use Tracy\Debugger;
use Tracy\ILogger;


/**
 * Sends the confirmation of a new account. A failed email does not undo the registration, it is only logged.
 */
readonly class SignUpEmailService
{
	public function __construct(
		private string $from,
		private Translator $translator,
		private Mailer $mailer,
		private TemplateFactory $templateFactory,
		private LinkGenerator $linkGenerator,
	) {
	}


	public function sendEmail(string $email, string $username, string $lang): void
	{
		try {
			$this->translator->setTranslate($lang);

			$template = $this->templateFactory->createTemplate();
			$template->setFile(__DIR__ . '/sign-up.latte');
			$template->setTranslator($this->translator);
			$template->lang = $lang;
			$template->username = $username;
			$template->signInLink = $this->linkGenerator->link('Sign:in', ['lang' => $lang]);

			$message = new Message;
			$message->setFrom($this->from)
				->addTo($email)
				->setSubject($this->translator->translate('Your account has been created'))
				->setHtmlBody($template->renderToString());

			$this->mailer->send($message);

		} catch (Throwable $e) {
			Debugger::log($e, ILogger::ERROR);
		}
	}
}
