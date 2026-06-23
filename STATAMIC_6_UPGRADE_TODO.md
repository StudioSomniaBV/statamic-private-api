# Statamic 6 Compatibility — TODO

> Plaats dit bestand in de root van de fork (`StudioSomniaBV/statamic-private-api`),
> naast `composer.json` en `README.md`. Het doet **niets** tot je de wijzigingen
> daadwerkelijk doorvoert in de code.

## Doel

De addon compatibel maken met **Statamic 6** door upstream PR
[tv2regionerne/statamic-private-api#40](https://github.com/tv2regionerne/statamic-private-api/pull/40/files)
te overnemen in onze fork (`force-upload-fix` branch of een nieuwe `statamic-6` branch).

## Strategie

We hebben twee parallelle aanpassingen in de fork:

1. **`force-upload-fix`** — onze eigen patch (delete-before-upload bypass).
2. **Statamic 6 compat** — upstream PR #40.

→ Beide moeten samengevoegd worden in één werkende branch voordat we de
`composer.json` van de hoofdapp naar Statamic 6 upgraden.

## Stappen

### 1. Branch aanmaken
```bash
git checkout force-upload-fix
git checkout -b statamic-6-compat
```

### 2. PR #40 mergen / cherry-picken
Optie A — upstream PR als patch toepassen:
```bash
curl -L https://github.com/tv2regionerne/statamic-private-api/pull/40.diff \
  | git apply --3way
```

Optie B — handmatig de diff overnemen vanuit
https://github.com/tv2regionerne/statamic-private-api/pull/40/files

### 3. Kernwijzigingen uit PR #40

**`composer.json`:**
```diff
- "statamic/cms": "^4.0 || ^5.0"
+ "statamic/cms": "^6.0"

- "orchestra/testbench": "^9.0",
- "pestphp/pest": "^2.4",
- "pestphp/pest-plugin-watch": "^2.0",
+ "orchestra/testbench": "^10.0",
+ "pestphp/pest": "^3.0",

- "larastan/larastan": "^2.9"
+ "larastan/larastan": "^3.0"
```

**Nieuwe file:** `src/Http/Controllers/ApiController.php`
(eigen base controller — upstream Statamic heeft `Statamic\Http\Controllers\API\ApiController` verplaatst/verwijderd in v6).

**Alle controllers** in `src/Http/Controllers/`:
- Vervang `use Statamic\Http\Controllers\API\ApiController;`
  door `use Tv2regionerne\StatamicPrivateApi\Http\Controllers\ApiController;`
- Diverse kleine aanpassingen voor Carbon imports, mimetype guessing, etc.

Zie volledige diff: https://patch-diff.githubusercontent.com/raw/tv2regionerne/statamic-private-api/pull/40.diff

### 4. Controleren dat onze `force-upload-fix` patch nog werkt
Onze delete-before-upload bypass / force-upload logica moet behouden blijven
en samenwerken met de v6 controllers.

### 5. Testen lokaal (optioneel)
```bash
composer install
vendor/bin/pest
```

### 6. Pas activeren wanneer hoofdapp naar Statamic 6 gaat
**Niet mergen naar `force-upload-fix`** zolang de productie-Statamic nog op v5 draait.
Houd `statamic-6-compat` als aparte branch klaar.

## Wanneer activeren?

- [ ] Hoofdapp Statamic upgrade naar v6 ingepland
- [ ] `statamic-6-compat` branch getest in staging
- [ ] `composer.json` hoofdapp wijzen naar deze branch:
  ```json
  "studiosomnia/statamic-private-api": "dev-statamic-6-compat"
  ```

## Referenties

- Upstream PR: https://github.com/tv2regionerne/statamic-private-api/pull/40
- Statamic 6 upgrade guide: https://statamic.dev/upgrade-guide/5-to-6
