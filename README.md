# Potok i webhook — szablon PHP dla Zapq

Jedna strona: nazwa metody API, wejście JSON, przycisk **Uruchom** i wynik odebranego webhooka.
PHP uruchamia potok jako zalogowany użytkownik. Zapq wysyła jego status do aplikacji,
a odbiornik sprawdza podpis i zapisuje wynik w SQLite.

```text
Formularz → run.php → API Zapq → potok → api/webhook.php → SQLite → strona wyniku
```

Odbiór działa bez otwartej przeglądarki. Przycisk **Odczytaj odebrany webhook** czyta lokalny
zapis; aplikacja nie odpytuje cyklicznie API Zapq. Nie wymaga własnego socketu, procesu
w tle, Composera ani budowania frontendu.

## Uruchomienie

1. Utwórz własne repo z [web_app_template](https://github.com/zapqio/web_app_template)
   przez **Use this template**. Katalog `public/` musi pozostać w korzeniu repo.
2. Dodaj repo w Zapq jako typ **Web**, utwórz aplikację i wdróż commit.
   Dostęp do strony wymaga `Applications.Open`, a uruchomienie potoku `Pipelines.Run`.
3. Przygotuj potok na dostępnym runnerze i przypisz mu metodę API. W formularzu podaj
   jej nazwę oraz JSON zgodny z wejściem potoku. Domyślne wartości ustawisz w `lib/config.php`.
4. W konfiguracji tego potoku dodaj webhook na adres:

   ```text
   https://<instancja>/<firma>/ui/<nazwa-aplikacji>/api/webhook.php
   ```

   Dla lokalnego Compose można użyć skonfigurowanego originu wewnętrznego, np.
   `http://app:8080/hdwr-demo/ui/<nazwa-aplikacji>/api/webhook.php`.
5. Przenieś ID webhooka i jego sekret z Zapq do pliku `webhook.json` w prywatnym
   katalogu wskazanym przez `ZAPQIO_APPLICATION_DATA_DIRECTORY`:

   ```json
   {"webhookId":"<ID webhooka>","secrets":["<sekret podpisu>"]}
   ```

   Plik ma należeć do UID aplikacji i mieć uprawnienia `0600`. Nie dodawaj go do Git
   ani `public/`. Host przechowuje dane w `/apps-data/<ApplicationId>` na trwałym wolumenie.
   Przy rotacji wpisz oba aktywne sekrety, a po okresie przejściowym usuń stary.
6. Otwórz aplikację, kliknij **Uruchom**, poczekaj na wykonanie potoku i wybierz
   **Odczytaj odebrany webhook**. Zapisz adres z `runId`, aby wrócić do wyniku później.

Instancja Zapq musi obsługiwać ścieżki `/ui/<aplikacja>/api/` bez sesji użytkownika
i przekazywać nagłówki `X-Webhook-*`. Pozostałe strony nadal wymagają sesji i uprawnień.
PHP wywołuje API przez zaufany nagłówek `X-Zapq-Internal-Api-Url`; host tego adresu musi
być dopuszczony w `AllowedHosts` instancji.

## Co zawiera webhook

Zdarzenie `pipelineRun.jobFinished` może przychodzić po kolejnych krokach. Pole
`data.status` opisuje **cały potok**; zakończenie pierwszego kroku nie oznacza sukcesu
całego uruchomienia. Odbiornik zapisuje `runId`, `status`, `lastOutput` oraz `steps`.
Statusy: `waiting`, `executing`, `ok`, `error`, `needsAttention`, `cancelled`.
`needsAttention` wymaga rozstrzygnięcia przez operatora i może później otrzymać nowy wynik.

Weryfikacja obejmuje ID webhooka, HMAC-SHA256 z `timestamp + "." + surowe body` oraz
świeżość podpisu (5 minut). Kluczem HMAC jest tekst sekretu, bez dekodowania hex.
Ponowienie tej samej dostawy otrzymuje 200 bez ponownego zapisu. Spóźnione starsze zdarzenie
nie zastępuje nowszego wyniku. Odpowiedź 200 jest wysyłana dopiero po trwałym zapisie;
błąd zapisu zwraca 503, aby Zapq ponowił dostawę.

Webhook dotrze także dla potoku uruchomionego bezpośrednio przez API. Strona wyniku pokazuje
tylko uruchomienia rozpoczęte przez tę aplikację przez danego użytkownika — przy starcie
zapisujemy powiązanie `runId` z jego ID. Samo poznanie `runId` nie daje dostępu do wyniku.
Lokalny odczyt wymaga sesji i `Applications.Open`; nie wymaga `Logs.View`.

`store_webhook()` w `lib/webhooks.php` to miejsce na własną obsługę biznesową. Tutaj jest
nią zapis wyniku. Dodatkowe lokalne zmiany zapisuj w tej samej transakcji co potwierdzenie
dostawy; dla wywołań zewnętrznych użyj idempotencji lub kolejki. Nie wywołuj w odbiorniku
`zapq_context()` — webhook nie ma sesji ani tokenu użytkownika.

## Pliki

| Plik | Rola |
|---|---|
| `public/index.php`, `public/assets/` | Jedyna strona aplikacji: uruchomienie i odczyt wyniku. |
| `public/run.php` | POST uruchamiający potok przez PHP, z CSRF i kluczem idempotencji. |
| `public/status.php` | GET wyniku z lokalnej bazy, tylko dla autora uruchomienia. |
| `public/api/webhook.php` | Odbiornik podpisanego webhooka, bez sesji użytkownika. |
| `lib/zapq.php` | Wywołanie API Zapq z tożsamością użytkownika przekazaną przez bramkę. |
| `lib/webhooks.php`, `lib/database.php` | Podpisy, deduplikacja, właściciel uruchomienia i SQLite. |
| `lib/config.php` | Jawne wartości domyślne formularza, bez sekretów. |

Baza `state.sqlite` i sekret pozostają poza kodem oraz `public/`. Wdrożenie nowego commita
zachowuje dane; rollback cofa kod, nie dane ani schemat. Formularz zachowuje klucz idempotencji
po utracie odpowiedzi: kliknij ponownie bez zmiany danych, aby odzyskać to samo uruchomienie.
Bez JavaScript formularz również uruchamia potok, zwracając JSON z `runId`; zapisany wynik
można odczytać pod `status.php?runId=<runId>` w sesji autora.

## Testy

PHP 8.4 z `pdo_sqlite` i `curl`; Node 24 jest potrzebny wyłącznie do testów strony.

```sh
php tests/check.php
node --test tests/app.test.mjs
```

Testy PHP obejmują podpis, rotację, duplikaty, kolejność zdarzeń, trwałość i uprawnienia do
wyniku. Test HTTP uruchamia dwa tymczasowe serwery PHP na loopback: aplikację i atrapę API
Zapq. Sprawdza rzeczywiste żądania, formularz multipart, surowy JSON i zapis wyniku.
Nie wymaga działającej instancji ani połączeń zewnętrznych. Testy zapisują dane tylko w TEMP.
Testy JS sprawdzają formularz, CSRF, ponowienie po utracie odpowiedzi i ręczny odczyt wyniku.

Bez lokalnego PHP (PowerShell):

```powershell
docker run --rm --network none --read-only --tmpfs /tmp -v "${PWD}:/template:ro" -w /template php:8.4.26-fpm-alpine3.24@sha256:78cd8de9970a9cd6ff4d98860a94eb5bf37dd2d5776b4785562dbfad01c29d5a php tests/check.php
```

GitHub Actions i GitLab CI wykonują te same testy. Nie budują obrazu aplikacji ani nie
wdrażają jej automatycznie. Test z prawdziwym Zapq opisuje [docs/agent.md](docs/agent.md).
