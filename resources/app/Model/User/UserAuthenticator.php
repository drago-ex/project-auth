<?php declare(strict_types=1);

namespace App\Model\User;

use Dibi\Exception;
use Drago\Permission\Role;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\Authorizator;
use Nette\Security\IdentityHandler;
use Nette\Security\IIdentity;
use Nette\Security\Passwords;
use Nette\Security\Permission;
use Nette\Security\SimpleIdentity;


readonly class UserAuthenticator implements Authenticator, IdentityHandler
{
	public function __construct(
		private Passwords $password,
		private UserRepository $userRepository,
		private bool $requireVerification = true,
		private ?Authorizator $authorizator = null,
	) {
	}


	/**
	 * Authenticates the user using the username and password.
	 * @throws AuthenticationException
	 * @throws Exception
	 */
	public function authenticate(string $username, string $password): SimpleIdentity
	{
		$user = $this->userRepository->findUserByEmail($username);

		if (!$user) {
			// Hashing takes as long as verifying, so the response time does not reveal which emails have an account.
			$this->password->hash($password);
			throw new AuthenticationException('User not found.');
		}

		if (!$this->password->verify($password, $user->password)) {
			throw new AuthenticationException('Incorrect password.');
		}

		// Checked after the password, so it does not tell strangers that the account exists.
		if ($this->requireVerification && $user->verifiedAt === null) {
			throw new AuthenticationException('The email address is not confirmed.', self::NotApproved);
		}

		if ($this->password->needsRehash($user->password)) {
			$user->password = $this->password->hash($password);
			$this->userRepository->table->save($user);
		}

		$user->offsetUnset('password');
		return new SimpleIdentity(id: $user->id, roles: $this->getRoles($user->id), data: $user->toArray());
	}


	public function sleepIdentity(IIdentity $identity): SimpleIdentity
	{
		$data = $identity->getData();
		$token = $data['token'] ?? null;

		return new SimpleIdentity($token);
	}


	/**
	 * Loads the user and their role when restoring identity.
	 * @throws Exception
	 */
	public function wakeupIdentity(IIdentity $identity): ?SimpleIdentity
	{
		$user = $this->userRepository->findUserByToken(
			(string) $identity->getId(),
		);

		if ($user === null) {
			return null;
		}

		$user->offsetUnset('password');
		return new SimpleIdentity(id: $user->id, roles: $this->getRoles($user->id), data: $user->toArray());
	}


	/**
	 * The roles of the user known to the ACL (a removed custom role may still be stored); "user" when none is left,
	 * so every signed-in user has the basics of the user role.
	 * @return list<string>
	 * @throws Exception
	 */
	private function getRoles(int $userId): array
	{
		$roles = $this->userRepository->getRolesByUser($userId);
		if ($this->authorizator instanceof Permission) {
			$roles = array_values(array_filter($roles, $this->authorizator->hasRole(...)));
		}

		return $roles ?: [Role::User];
	}
}
