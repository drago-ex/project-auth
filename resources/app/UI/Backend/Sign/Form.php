<?php

declare(strict_types=1);

namespace App\UI\Backend\Sign;

use Drago\Form\Autocomplete;
use Drago\Form\Forms;
use Drago\Form\Input;


class Form extends Forms
{
	/**
	 * Adds a password input field to the form.
	 */
	public function addPasswordField(): Input
	{
		return $this->addPasswordInput('password', 'Password')
			->setPlaceholder('Your password')
			->setRequired('Please enter your password.');
	}


	/**
	 * Adds a password confirmation input field to the form.
	 */
	public function addPasswordConfirmationField(): Input
	{
		// Create a password confirmation input field
		$passwordField = $this->addPasswordInput('verify', 'Password to check')
			->setPlaceholder('Re-enter password')
			->setRequired('Please enter your password to check.');

		// Check if 'password' field exists in the form
		if (!isset($this['password'])) {
			throw new \InvalidArgumentException('Password field is required for password confirmation.');
		}

		// Add the rule to check if the 'verify' field matches the 'password' field
		$passwordField->addRule($this::Equal, 'Passwords do not match.', $this['password']);

		return $passwordField;
	}


	/**
	 * Adds an email input field to the form.
	 */
	public function addEmailField(): Input
	{
		return $this->addEmailInput('email', 'Email')
			->setPlaceholder('Email address')
			->setRequired('Please enter your email address.')
			->setAutocomplete(Autocomplete::Email)
			->addRule(self::Email);
	}
}
