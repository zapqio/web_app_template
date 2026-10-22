# Praca z szablonem PHP

Szablon ma pozostać mały: jedna strona do uruchomienia potoku i odczytu webhooka.
Nie dodawaj przykładów notatek, plików, osobnego podglądu logów ani pollingu API.

## Konfiguracja

- Repozytorium Web, gałąź, ID aplikacji i URL instancji z `PathBase`.
- Nazwa metody API, testowy potok, dostępny runner i poprawne wejście JSON.
- Webhook potoku wskazujący `/ui/<aplikacja>/api/webhook.php`.
- ID webhooka i sekret w prywatnym `webhook.json` w katalogu danych aplikacji.
- Konto z `Applications.Open` i `Pipelines.Run`. Do wdrożenia odpowiednie prawa
  repozytorium, aplikacji oraz `Deployments.Send`.

Sekretów ani danych logowania nie zapisuj w repo, URL, logach, raportach ani HTML.
Komunikaty commitów pisz po angielsku. Każdy `if` i `else` ma mieć klamry.

## Weryfikacja

1. Uruchom `php tests/check.php` oraz `node --test tests/app.test.mjs`.
2. Wdróż uzgodniony commit i sprawdź `Applied` oraz SHA bieżącego wdrożenia.
3. Otwórz stronę w sesji Zapq i uruchom potok. Sprawdź autora uruchomienia.
4. Odczytaj webhook. Porównaj `runId`, status całego potoku, kroki i `lastOutput`.
   Przy potoku z kilkoma krokami pierwszy webhook nie może udawać końcowego sukcesu.
5. Zamknij stronę po starcie, a po zakończeniu wróć pod adres z `runId`.
   Odbiór ma działać bez przeglądarki i bez odpytywania API w tle.
6. Sprawdź powtórzoną dostawę (200, bez drugiego zapisu), zmienione body i błędny podpis
   (403), a także brak dostępu innego użytkownika do wyniku (404).
7. Po kolejnym wdrożeniu wcześniejszy wynik powinien pozostać w SQLite.

Test HTTP w tym repo izoluje PHP i używa atrapy API. Nie zastępuje testu bramki,
CSRF ani prawdziwej kolejki webhooków w Zapq. Raport rozróżnia te dwa rodzaje testów.
Publikacja i wdrożenie wynikają z bieżącego polecenia użytkownika.
