const form = document.querySelector('#pipeline');
const submit = form.querySelector('button[type="submit"]');
const refresh = document.querySelector('#refresh');
const message = document.querySelector('#message');
const resultError = document.querySelector('#result-error');
let runId;

const validRunId = value => /^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i.test(value);
const newKey = () => { form.elements.idempotencyKey.value = crypto.randomUUID(); };

async function readResult() {
    const id = runId;
    refresh.disabled = true;
    resultError.textContent = '';
    try {
        // Local SQLite only. No polling of Zapq's run-status API.
        const response = await fetch(`status.php?runId=${encodeURIComponent(id)}`, {
            credentials: 'same-origin', cache: 'no-store', redirect: 'error'
        });
        if (!response.ok) {
            throw new Error(response.status === 404
                ? 'Nie znaleziono Twojego uruchomienia w tej aplikacji.'
                : `Odczyt webhooka: HTTP ${response.status}. Sprawdź sesję i dostęp do aplikacji.`);
        }

        const result = await response.json();
        if (id !== runId) {
            return;
        }

        document.querySelector('#run-status').textContent = result.webhookReceived ? result.status : 'Oczekiwanie na webhook';
        document.querySelector('#received-at').textContent = result.receivedAt
            ? `Odebrano: ${new Date(result.receivedAt).toLocaleString('pl-PL')}` : '';
        document.querySelector('#output').textContent = result.lastOutput ?? 'Brak wyniku.';
        document.querySelector('#webhook').textContent = result.webhook
            ? JSON.stringify(result.webhook, null, 2) : 'Webhook jeszcze nie dotarł. Odczytaj wynik ponownie za chwilę.';
    } catch (error) {
        if (id === runId) {
            resultError.textContent = error.message;
        }
    } finally {
        if (id === runId) {
            refresh.disabled = false;
        }
    }
}

function showResult(id) {
    runId = id;
    document.querySelector('#run-id').textContent = id;
    document.querySelector('#run-status').textContent = 'Oczekiwanie na webhook';
    for (const selector of ['#received-at', '#output', '#webhook']) {
        document.querySelector(selector).textContent = '';
    }

    document.querySelector('#result').hidden = false;
    const url = new URL(location.href);
    url.searchParams.set('runId', id);
    history.replaceState(null, '', url);
    return readResult();
}

form.addEventListener('submit', async event => {
    event.preventDefault();
    if (submit.disabled) {
        return;
    }

    try {
        JSON.parse(form.elements.input.value);
    } catch {
        message.textContent = 'Wejście musi być poprawnym JSON.';
        return;
    }

    submit.disabled = true;
    message.textContent = 'Uruchamianie…';
    try {
        // The hidden antiforgery field travels with the form. Keep the same key after a lost response.
        const response = await fetch('run.php', {
            method: 'POST', body: new FormData(form), credentials: 'same-origin', cache: 'no-store', redirect: 'error'
        });
        if (response.status === 409) {
            newKey();
            throw new Error('Klucz był już użyty z innym wejściem. Wygenerowano nowy; uruchom ponownie.');
        }

        if (response.status >= 500) {
            throw new TypeError('Uncertain start');
        }

        if (!response.ok) {
            throw new Error(`Uruchomienie: HTTP ${response.status}. Sprawdź metodę API, wejście i prawo Pipelines.Run.`);
        }

        const started = await response.json();
        if (!validRunId(started.runId)) {
            throw new TypeError('Invalid start response');
        }

        newKey();
        message.textContent = 'Potok uruchomiony. PHP czeka na webhook z wynikiem.';
        await showResult(started.runId);
    } catch (error) {
        message.textContent = error instanceof TypeError
            ? 'Nie wiadomo, czy potok wystartował. Kliknij ponownie bez zmiany danych — ten sam klucz zapobiegnie drugiemu uruchomieniu.'
            : error.message;
    } finally {
        submit.disabled = false;
    }
});

form.addEventListener('input', newKey);
refresh.addEventListener('click', readResult);
const initial = new URLSearchParams(location.search).get('runId');
if (initial && validRunId(initial)) {
    showResult(initial);
}
