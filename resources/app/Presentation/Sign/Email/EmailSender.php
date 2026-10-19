<?php

declare(strict_types=1);

namespace App\Presentation\Sign\Email;

use Drago\Localization\Translator;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Throwable;
use Tracy\Debugger;
use Tracy\ILogger;


/**
 * Renders a translated email template and sends it. A failed email is logged and does not stop the action
 * that sent it (e.g. a registration stays valid when the mail server is down).
 */
readonly class EmailSender
{
	public function __construct(
		private string $from,
		private Translator $translator,
		private Mailer $mailer,
		private TemplateFactory $templateFactory,
	) {
	}


	/**
	 * @param array<string, mixed> $parameters
	 */
	public function send(string $to, string $subject, string $file, array $parameters, string $lang): void
	{
		try {
			$this->translator->setTranslate($lang);

			$template = $this->templateFactory->createTemplate();
			$template->setFile($file);
			$template->setTranslator($this->translator);
			foreach ($parameters + ['lang' => $lang, 'subject' => $subject] as $name => $value) {
				$template->$name = $value;
			}

			$message = new Message;
			$message->setFrom($this->from)
				->addTo($to)
				->setSubject($this->translator->translate($subject))
				->setHtmlBody($template->renderToString());

			$this->mailer->send($message);

		} catch (Throwable $e) {
			Debugger::log($e, ILogger::ERROR);
		}
	}
}
