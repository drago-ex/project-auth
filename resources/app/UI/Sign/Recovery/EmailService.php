<?php

declare(strict_types=1);

namespace App\UI\Sign\Recovery;

use Drago\Localization\Translator;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Tracy\Debugger;


/** Service for sending password recovery emails. */
readonly class EmailService
{
	public function __construct(
		private Translator $translator,
		private Mailer $mailer,
		private TemplateFactory $templateFactory,
	) {
	}


	/** Sends the password recovery email. */
	public function sendEmail(string $email, string $token): void
	{
		$template = $this->templateFactory->createTemplate();
		$template->setFile(__DIR__ . '/email.latte');
		$template->setTranslator($this->translator);
		$template->token = $token;

		$message = new Message;
		$message->setFrom('no-reply@email.com')
			->addTo($email)
			->setSubject($this->translator->translate('Request to reset password'))
			->setHtmlBody($template->renderToString());

		try {
			$this->mailer->send($message);

		} catch (\Throwable $e) {
			Debugger::log($e, Debugger::ERROR);
		}
	}
}
