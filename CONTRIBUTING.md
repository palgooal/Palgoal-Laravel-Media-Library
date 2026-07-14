# Contributing

Thanks for considering a contribution to `palgoal/media-library`.

## Ground rules

- **Namespace and package name are fixed.** `Palgoal\MediaLibrary\*` and
  `palgoal/media-library` should not change.
- **Backward compatibility.** This package aims to support Laravel 10, 11,
  and 12 simultaneously. Avoid APIs/features only available in the newest
  supported Laravel version unless guarded appropriately.
- **No dependency on any specific host application.** Nothing under `src/`,
  `resources/`, `routes/`, or `public/` should assume `App\...` classes,
  a specific admin theme, or any file that isn't part of this package.
  Anything host-specific must go through `config/media-library.php`.
- **Security-sensitive changes** (file upload handling, authorization,
  SVG/mime handling) should include a test that would have caught the
  issue being fixed, and ideally a note in `SECURITY.md` if it changes a
  documented risk area.

## Getting set up

```bash
git clone https://github.com/palgooal/Palgoal-Laravel-Media-Library.git
cd Palgoal-Laravel-Media-Library
composer install
```

## Running tests

```bash
composer test              # vendor/bin/phpunit
composer format-test       # vendor/bin/pint --test  (check formatting)
composer format             # vendor/bin/pint         (fix formatting)
```

The test suite uses [Orchestra Testbench](https://github.com/orchestral/testbench)
to boot a minimal Laravel application in-memory (SQLite) — no separate
Laravel installation is required to run the tests.

## Pull requests

1. Add or update tests for any behavior change — especially anything
   touching uploads, deletion, or authorization.
2. Run `composer format-test` and fix anything Pint flags before opening
   the PR.
3. Update `CHANGELOG.md` under `[Unreleased]`.
4. Describe any breaking change explicitly in the PR description, even a
   small one (e.g. a validation rule becoming stricter).

## Reporting bugs

Open a GitHub issue with: the package version, the Laravel version, PHP
version, and a minimal reproduction. For security vulnerabilities, see
`SECURITY.md` instead of opening a public issue.
