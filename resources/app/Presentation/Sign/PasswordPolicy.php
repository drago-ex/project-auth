<?php declare(strict_types=1);

namespace App\Presentation\Sign;

use Nette\Forms\Control;
use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Form;
use function sprintf, strlen;


/**
 * Rules for new passwords, shared by registration, password recovery and the account.
 * Length makes a password strong, so composition rules are not required and spaces are allowed
 * (NIST SP 800-63B, OWASP ASVS). Bcrypt uses only the first 72 bytes, so longer passwords are rejected.
 */
final class PasswordPolicy
{
	public const int MinLength = 12,
		MaxBytes = 72;


	public static function apply(BaseControl $input): BaseControl
	{
		return $input
			->addRule(Form::MinLength, 'Password must be at least %d characters long.', self::MinLength)
			->addRule([self::class, 'fitsMaxBytes'], 'The password is too long.');
	}


	public static function fitsMaxBytes(Control $input): bool
	{
		return strlen((string) $input->getValue()) <= self::MaxBytes;
	}


	/** Returns an error message, or null when the password meets the rules (for command-line tools). */
	public static function validate(#[\SensitiveParameter] string $password): ?string
	{
		if (mb_strlen($password) < self::MinLength) {
			return sprintf('Password must be at least %d characters long.', self::MinLength);
		}

		return strlen($password) > self::MaxBytes ? 'The password is too long.' : null;
	}
}
