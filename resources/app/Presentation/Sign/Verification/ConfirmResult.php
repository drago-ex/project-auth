<?php declare(strict_types=1);

namespace App\Presentation\Sign\Verification;


enum ConfirmResult
{
	/** The email address of a new account was confirmed. */
	case Verified;

	/** The new email address was confirmed and is now used by the account. */
	case EmailChanged;

	/** Another account started using the new email address in the meantime. */
	case EmailTaken;

	/** The link is unknown, already used or expired. */
	case Invalid;
}
