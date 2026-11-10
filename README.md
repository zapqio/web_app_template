# Szablon aplikacji PHP dla Zapq

Jedna strona do uruchomienia potoku i odczytu jego wyniku. PHP korzysta z sesji
Zapq, odbiera podpisany webhook i zapisuje wynik w SQLite, także po zamknięciu przeglądarki.

## Zacznij

1. Wybierz **Use this template → Create a new repository** i sklonuj własne repozytorium.
2. Zachowaj katalog `public/` w katalogu głównym repozytorium.
3. Przejdź przez [publikowanie aplikacji](https://docs.zapq.io/pl/guides/applications).
4. Przed uruchomieniem przykładu [skonfiguruj potok i webhook](https://docs.zapq.io/pl/technical/php-applications#application-pipeline). Plik z sekretem w prywatnym katalogu danych przygotowuje operator instancji.

## Testy lokalne

Wymagania: PHP 8.4 z `pdo_sqlite` i `curl` oraz Node.js 24. Uruchom z katalogu repozytorium:

```sh
php tests/check.php
node --test tests/app.test.mjs
```

Szablon nie wymaga Composera ani budowania frontendu. Testy nie publikują aplikacji.

## Dokumentacja

- [Opublikuj aplikację webową](https://docs.zapq.io/pl/guides/applications): repozytorium, publikacja i aktualizacje w panelu.
- [Tworzenie aplikacji PHP](https://docs.zapq.io/pl/technical/php-applications): kod, sesja, webhooki, dane i testowanie.
