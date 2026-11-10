# PHP application template for Zapq

A single page to run a pipeline and read its result. PHP uses the Zapq session,
receives a signed webhook, and stores the result in SQLite, even after the browser is closed.

## Get started

1. Select **Use this template → Create a new repository** and clone your repository.
2. Keep the `public/` directory at the repository root.
3. Follow the [application publishing guide](https://docs.zapq.io/guides/applications).
4. Before running the example, [configure the pipeline and webhook](https://docs.zapq.io/technical/php-applications#application-pipeline). The instance operator prepares the secret file in the application's private data directory.

## Local tests

Requirements: PHP 8.4 with `pdo_sqlite` and `curl`, and Node.js 24. Run from the repository root:

```sh
php tests/check.php
node --test tests/app.test.mjs
```

The template does not require Composer or a frontend build. Tests do not publish the application.

## Documentation

- [Publish a web application](https://docs.zapq.io/guides/applications): repository setup, publishing, and updates in the panel.
- [Developing PHP applications](https://docs.zapq.io/technical/php-applications): code, sessions, webhooks, data, and testing.
