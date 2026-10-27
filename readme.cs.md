# Drago Project Auth

Balíček pro přihlašování a přístup uživatelů v projektu Drago.
Poskytuje kompletní autentizační vrstvu: přihlášení, registraci, obnovu hesla
a řízení přístupu. Je navržený jako modulární rozšíření pro projekty postavené
na ekosystému Drago a Nette Frameworku.

[![PHP version](https://badge.fury.io/ph/drago-ex%2Fproject-auth.svg)](https://badge.fury.io/ph/drago-ex%2Fproject-auth)
[![Tests](https://github.com/drago-ex/project-auth/actions/workflows/tests.yml/badge.svg)](https://github.com/drago-ex/project-auth/actions/workflows/tests.yml)
[![Coding Style](https://github.com/drago-ex/project-auth/actions/workflows/coding-style.yml/badge.svg)](https://github.com/drago-ex/project-auth/actions/workflows/coding-style.yml)

## Požadavky
- PHP >= 8.3, < 9
- Nette Framework
- Composer
- Bootstrap
- Naja
- Node.js
- Základní balíčky Drago Project

## Instalace
```bash
composer require drago-ex/project-auth
```

### Instalace přes npm
Přihlašovací rozhraní potřebuje přepínač motivů a ikony Font Awesome:
```bash
npm install theme-switcher-compostrap @fortawesome/fontawesome-free
```

## Soubory projektu
Kopírování souborů obstarává automaticky [drago-ex/project-tools](https://github.com/drago-ex/project-tools),
který musí být v projektu nainstalovaný. Bez něj zkopírujte soubory ručně podle sekce `copy`
v `composer.json` tohoto balíčku. Pro přeskočení balíčku nastavte `"skip": true` v
`extra.drago-tools.packages.<package-name>` v kořenovém `composer.json`.

## Migrace databáze
```bash
php vendor/bin/migration db:migrate --packages
```
Balíček má `migration-priority` 10, takže tabulka `users` existuje dřív než balíčky, které ji používají.

## Funkce
- Přihlášení a odhlášení uživatele
- Registrace uživatele
- Obnova a změna hesla
- Ověření e-mailové adresy (po registraci a při změně adresy)
- Omezení počtu pokusů o přihlášení, registraci a obnovu hesla
- Práce s identitou uživatele
- Integrace s Nette Security a DI
- Hotové komponenty přihlašovacího rozhraní

## Konfigurace
Parametry v `app/Presentation/Sign/conf.neon`:
- `authMailFrom` - odesílatel e-mailů k účtu, použijte adresu své domény
- `authRequireEmailVerification` - nový účet potvrzuje svou e-mailovou adresu odkazem (výchozí `true`)
- sekce `mail` - připojení k SMTP

Po přihlášení se uživatel dostane do administrace (`:Backend:Admin:`), nebo na úvodní stránku webu (`:Front:Home:`),
pokud mu ACL administraci nepovoluje. Pro změnu upravte konstanty `SignedInDestination` a `FallbackDestination`
v `app/Presentation/Sign/SignPresenter.php`.

Za reverzní proxy nastavte `http: proxy:`, jinak se limity podle IP adresy počítají pro adresu proxy.

## Omezení pokusů
Pokusy se počítají v cache aplikace; smazáním `temp/cache` se vynulují.

| Akce | Limit |
|---|---|
| Přihlášení | 10 neúspěšných pokusů na e-mail a 30 na IP adresu za 15 minut |
| Registrace | 10 pokusů na IP adresu za hodinu |
| Obnova hesla | 5 žádostí na e-mail a 20 na IP adresu za hodinu, 10 chybných kódů za 15 minut |
| Nový potvrzovací odkaz | 3 žádosti na e-mail a 10 na IP adresu za hodinu |

## Bezpečnost
- Hesla hashuje `Nette\Security\Passwords`; pravidla jsou v `Sign/PasswordPolicy` (nejméně 12 znaků).
- Přihlášení ani obnova hesla neprozradí, jestli k e-mailu existuje účet.
- Změna nebo obnova hesla odhlásí uživatele ze všech ostatních relací.
- Kódy pro obnovu a potvrzovací odkazy se ukládají jako hash, mají omezenou platnost a fungují jen jednou.
- Se zapnutým ověřením e-mailu odpoví registrace u už registrované adresy stejně a jen pošle e-mail jejímu majiteli;
  bez ověření oznámí, že je adresa obsazená (nový účet by byl hned použitelný).
- Odkaz na nový potvrzovací e-mail se zobrazí až po zadání správného hesla nepotvrzeného účtu.
- Dvoufázové ověření součástí není.

## E-maily
Šablony e-mailů jsou v `Sign/Email/` (společný layout `@email.latte`, používá ho i `Sign/Recovery/email.latte`).

Pro zachytávání e-mailů při vývoji přidejte [Mailpit](https://mailpit.axllent.org) do `docker-compose.override.yaml`:
```yaml
  mailpit:
    image: axllent/mailpit:v1.27
    restart: always
    ports:
      - '8025:8025'
```
a nasměrujte na něj odesílání v `Sign/conf.neon`:
```neon
mail:
	smtp: true
	host: mailpit
	port: 1025
```
E-maily se zobrazí na http://localhost:8025.

## Přidání nového uživatele do databáze
Ověří e-mail a heslo (`PasswordPolicy`), zahashuje heslo, vygeneruje token a zajistí jedinečnost e-mailu.
```bash
php vendor/bin/create-user <username> <email> <password>
```

## Zabezpečení části webu
```php
use App\Presentation\Accessory\RequireLogged;
use Nette\Application\UI\Presenter;

final class SecurePresenter extends Presenter
{
	use RequireLogged;
}
```
Nepřihlášený návštěvník se dostane na přihlašovací stránku a po přihlášení zpět na požadovanou stránku.

## Oprávnění
Balíček funguje bez oprávnění i bez [drago-ex/permission](https://github.com/drago-ex/permission).
Pro základní oprávnění nainstalujte drago-ex/permission a vygenerujte pravidla přístupu z [`permission.php`](permission.php):
```bash
php vendor/bin/create-permission --packages
```

Uživatelé nemají vlastní role, dokud je nevrací `getRolesByUser()` v `app/Model/User/UserRepository.php`.
Pro načítání rolí z databáze [drago-ex/project-permission](https://github.com/drago-ex/project-permission)
metodu implementujte (v projektu sestaveném přes [netis](https://github.com/netis-cms/netis) to udělá project-preset):
```php
use App\Presentation\Backend\Permission\Component\Roles\RolesEntity;
use App\Presentation\Backend\Permission\Component\Users\UsersRolesEntity;
use Drago\Permission\Role;

public function getRolesByUser(int $userId): array
{
	$roles = $this->getConnection()
		->select('r.*')->from(RolesEntity::Table)->as('r')
		->innerJoin(UsersRolesEntity::Table)->as('ur')->on('ur.role_id = r.id')
		->where('ur.%n = ?', UsersRolesEntity::ColumnUserId, $userId)
		->fetchPairs(value: RolesEntity::ColumnName);

	$roles = array_values($roles);
	return $roles ?: [Role::RoleUser];
}
```
