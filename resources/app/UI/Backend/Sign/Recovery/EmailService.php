<?php

declare(strict_types=1);

namespace App\UI\Backend\Sign\Recovery;

use Drago\Localization\Translator;
use Nette\Application\UI\TemplateFactory;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Tracy\Debugger;


class EmailService
{
	public string $email;
	public string $token;
	private Translator $translator;


	public function __construct(
		private readonly Mailer $mailer,
		private readonly TemplateFactory $templateFactory,
	) {
	}


	public function setTranslator(Translator $translator): void
	{
		$this->translator = $translator;
	}


	public function sendEmail(): void
	{
		$template = $this->templateFactory->createTemplate();
		assert($template instanceof EmailServiceTemplate);
		$template->setFile(__DIR__ . '/email.latte');
		$template->setTranslator($this->translator);
		$template->token = $this->token;

		$message = new Message;
		$message->setFrom('no-reply@email.com')
			->addTo($this->email)
			->setSubject($this->translator->translate('Request to reset password'))
			->setHtmlBody($template->renderToString());

		try {
			$this->mailer->send($message);

		} catch (\Throwable $e) {
			Debugger::log($e, Debugger::ERROR);
		}
	}
}
