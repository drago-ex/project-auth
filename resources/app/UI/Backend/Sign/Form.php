<?php

declare(strict_types=1);

namespace App\UI\Backend\Sign;

use Drago\Form\Autocomplete;
use Drago\Form\Forms;
use Drago\Form\Input;


/** Sign in form. */
class Form extends Forms
{
	/** Adds a password input field. */
	public function addPasswordField(): Input
	{
		return $this->addPasswordInput('password', 'Password')
			->setPlaceholder('Your password')
			->setRequired('Please enter your password.');
	}


	/** Adds a password confirmation input field. */
	public function addPasswordConfirmationField(): Input
	{
		$passwordField = $this->addPasswordInput('verify', 'Password to check')
			->setPlaceholder('Re-enter password')
			->setRequired('Please enter your password to check.');

		if (!isset($this['password'])) {
			throw new \InvalidArgumentException('Password field is required for password confirmation.');
		}

		$passwordField->addRule($this::Equal, 'Passwords do not match.', $this['password']);
		return $passwordField;
	}


	/** Adds an email input field. */
	public function addEmailField(): Input
	{
		return $this->addEmailInput('email', 'Email')
			->setPlaceholder('Email address')
			->setRequired('Please enter your email address.')
			->setAutocomplete(Autocomplete::Email)
			->addRule(self::Email);
	}
}
