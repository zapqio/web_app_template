<?php
declare(strict_types=1);
$config = require dirname(__DIR__) . '/lib/config.php';
header('Cache-Control: no-store');

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Potok i webhook — Zapq</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main>
    <h1>Potok i webhook</h1>
    <p>Uruchom potok. PHP odbierze webhook ze statusem całego uruchomienia i zapisze wynik.</p>
    <?php if (!isset($_SERVER['HTTP_X_ZAPQ_ACCESS_TOKEN'])) { ?>
        <p role="alert">Otwórz aplikację przez Zapq.</p>
    <?php } else { ?>
        <form id="pipeline" method="post" action="run.php">
            <input type="hidden" name="<?= escape($_SERVER['HTTP_X_ZAPQ_CSRF_FIELD']) ?>" value="<?= escape($_SERVER['HTTP_X_ZAPQ_CSRF_TOKEN']) ?>">
            <input type="hidden" name="idempotencyKey" value="<?= bin2hex(random_bytes(16)) ?>">
            <label for="method">Metoda API potoku</label>
            <input id="method" name="method" value="<?= escape($config['api_method']) ?>" pattern="[a-zA-Z0-9_\-]{1,128}" maxlength="128" required autocomplete="off">
            <label for="input">Wejście JSON</label>
            <textarea id="input" name="input" rows="6" maxlength="65536" required spellcheck="false"><?= escape($config['input_json']) ?></textarea>
            <div class="actions"><button type="submit">Uruchom</button></div>
        </form>
        <p id="message" role="status"></p>
        <section id="result" hidden aria-live="polite">
            <h2>Wynik uruchomienia</h2>
            <p>ID: <code id="run-id"></code></p>
            <p>Status całego potoku: <strong id="run-status"></strong></p>
            <p id="received-at"></p>
            <p>Webhook jest odbierany automatycznie. Przycisk odczytuje zapisany wynik.</p>
            <div class="actions"><button id="refresh" class="secondary" type="button">Odczytaj odebrany webhook</button></div>
            <p id="result-error" role="alert"></p>
            <h3>Wynik potoku</h3>
            <pre id="output"></pre>
            <details>
                <summary>Odebrany webhook</summary>
                <pre id="webhook"></pre>
            </details>
        </section>
        <script type="module" src="assets/app.js"></script>
    <?php } ?>
</main>
</body>
</html>
