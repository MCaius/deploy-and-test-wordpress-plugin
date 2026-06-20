# AI Agent Notes

This repository keeps AI-agent guidance in version control on purpose. The plugin was prepared with AI assistance, and this file is intended to make future AI-assisted maintenance safer and more consistent for the original maintainer or anyone who forks the project.

This is not a contribution guide. The repository is public, but it is not currently managed as an open contribution project.

## Project Scope

This folder contains a reusable WordPress plugin named `Deploy & Test`.

The plugin lives in:

```text
deploy-and-test/
```

Keep the plugin generic. Do not introduce client names, personal domains, local machine paths, private repository names, or organization-specific assumptions.

## Architecture

- Main plugin file: `deploy-and-test/deploy-and-test.php`
- Admin bootstrap: `deploy-and-test/includes/deploy-and-test-admin.php`
- Admin modules: `deploy-and-test/includes/admin/`
- Settings/actions/GitHub modules: `deploy-and-test/includes/settings.php`, `deploy-and-test/includes/actions.php`, `deploy-and-test/includes/github.php`
- Admin CSS: `deploy-and-test/includes/deploy-and-test-admin.css`
- Admin JS/polling: `deploy-and-test/includes/deploy-and-test-admin.js`
- How-to content: `deploy-and-test/includes/how-to-use-page/`
- Upload zip builder: `scripts/build-zip.mjs`
- PHP coding standard config: `phpcs.xml.dist`
- CI workflow: `.github/workflows/ci.yml`

## Security

- Do not store GitHub App private keys in the database.
- Do not expose private keys in JavaScript or admin HTML.
- GitHub App secrets should be configured through `wp-config.php`.
- Prefer `DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY_PATH` over inline private keys.
- Keep nonces on all admin POST/AJAX actions.
- Keep capability checks on admin pages, admin-post handlers, and AJAX handlers.
- Do not log secrets, private keys, installation tokens, JWTs, or full authorization headers.

## Coding

- Prefix new PHP functions/options/hooks with `deploy_and_test_`.
- Keep workflow filenames configurable through the Connection tab.
- Use WordPress escaping helpers for output.
- Use WordPress sanitization helpers for POST data.
- Run `php -l` after editing PHP.
- Run `node --check` after editing JS.
- Run `composer lint:php` when PHPCS dependencies are installed.
- Do not make broad formatting-only changes unless the current task is specifically PHPCS cleanup.

## Public Repository Expectations

- Keep `README.md`, `LICENSE`, `composer.json`, `composer.lock`, `phpcs.xml.dist`, and `.github/workflows/ci.yml` suitable for public GitHub.
- Keep `TO-DO.md`, `vendor/`, `dist/`, `.phpcs-cache`, `.DS_Store`, and local secrets out of Git.
- This project may accept forks under GPL-2.0-or-later, but it should not imply active third-party contribution support unless that changes later.

## Build

- Use `npm run build:zip` to create `dist/deploy-and-test.zip`.
- The upload zip should contain only the `deploy-and-test/` plugin runtime files.
- Do not include repo docs, scripts, `.git`, generated manifests, or local config in the WordPress upload zip.
