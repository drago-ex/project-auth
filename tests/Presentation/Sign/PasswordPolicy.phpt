<?php

/**
 * Test: App\Presentation\Sign\PasswordPolicy
 */

declare(strict_types=1);

use App\Presentation\Sign\PasswordPolicy;
use Nette\Forms\Form;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


function validatePassword(string $password): array
{
	$form = new Form;
	$input = $form->addPassword('password');
	PasswordPolicy::apply($input);
	$input->setValue($password);
	$input->validate();
	return $input->getErrors();
}


test('A long passphrase with spaces and no special characters is accepted', function () {
	Assert::same([], validatePassword('correct horse battery staple'));
	Assert::null(PasswordPolicy::validate('correct horse battery staple'));
});


test('Passwords shorter than 12 characters are rejected', function () {
	Assert::same(['Password must be at least 12 characters long.'], validatePassword('Short1!pass'));
	Assert::same('Password must be at least 12 characters long.', PasswordPolicy::validate('Short1!pass'));
});


test('Passwords over 72 bytes are rejected, counting multibyte characters as bytes', function () {
	Assert::same([], validatePassword(str_repeat('a', 72)));
	Assert::same(['The password is too long.'], validatePassword(str_repeat('a', 73)));
	Assert::same(['The password is too long.'], validatePassword(str_repeat('č', 37)));
	Assert::same('The password is too long.', PasswordPolicy::validate(str_repeat('č', 37)));
});
