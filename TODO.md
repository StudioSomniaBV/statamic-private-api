# TODO / status — statamic-private-api fork

E�n bron van waarheid voor deze fork. Vervangt eerdere losse TODO-bestanden.

Deze fork is een kopie van tv2regionerne/statamic-private-api met eigen
aanpassingen. De package-naam in `composer.json` is BEWUST hetzelfde gebleven
(`tv2regionerne/statamic-private-api`), zodat de hoofdapp alleen een
`repositories`-entry naar deze fork nodig heeft. JB haakt aan op `dev-main`.

Alle wijzigingen staan op de `main` branch. (Geen aparte feature-branches —
houd het simpel zolang er één persoon aan werkt.)

---

## ✅ Al doorgevoerd (staat live in main)

### DELETE assets fix
`AssetsController::destroy()` gebruikt nu:

    $this->authorize('delete', $asset);

in plaats van het originele:

    $this->authorize('delete', [AssetContract::class, $container]);

De originele variant crashte met een 500 (ArgumentCountError in Statamic's
`AssetPolicy` bij het augmenteren van de container vóór de delete-check).
Getest: POST→200, GET→200, DELETE→204, GET→404.

> LET OP: dit is een EIGEN fix en staat LOS van PR #40. PR #40 gaat
> uitsluitend over Statamic 6-compatibiliteit, niet over deze policy-fix.

---

## ⬜ Nog te doen

### 1. Localization-endpoints (entries + terms)
Klaargezette bestanden: 3 controllers + routes-instructie (zie
`fork-localization-bestanden.zip`).

Doel: de sync-tool kan dan zelf nieuwe taalversies van entries én terms
aanmaken via de API. Nu moet dat nog handmatig in de Statamic CP, omdat de
basis-addon `store` vastzit aan de huidige site. De endpoints gebruiken
`makeLocalization()` zodat de origin-chain intact blijft (voorkomt de eerdere
PT `filter[cities]` bug).

- 3 controllers → `src/Http/Controllers/`
- 3 route-toevoegingen → `routes/api.php` (zie INSTRUCTIES-routes.txt)
- Geen ServiceProvider-wijziging nodig.

**Na toevoegen TESTEN** met de `statamic-crud-tester` edge function
(POST/GET/DELETE op test-entry én test-term in een niet-default site) VOORDAT
JB deployt. Let extra op de term-endpoints — Statamic's term-localization
methodes verschillen licht tussen versies.

### 2. (Geparkeerd) `?force=true` upsert op asset-upload
Re-publishes kosten nu 2 calls (POST→422→PATCH). Een `?force=true` op
`AssetsController::store()` brengt dat naar 1 call. Bij 50 assets × 7 talen
scheelt dat ~350 calls per tour-republish.

Implementeer als **delete + store**, NIET door naar `update()` te routeren:
`update()` vervangt alleen metadata, niet de bytes — dat zou stille
datacorruptie geven (200 OK terwijl de oude MP3 blijft staan). In het
force-pad moet de `delete`-permissie apart geautoriseerd worden naast `store`.

Geen bug, puur optimalisatie. Huidige client-side fallback werkt en is getest.

---

## 📅 Statamic 6-plan

Statamic 5 krijgt na december 2026 geen security updates meer → upgrade naar
Statamic 6 moet sowieso gebeuren.

De originele addon is hier nog niet klaar voor. Open upstream PR #40
(tv2regionerne/statamic-private-api#40) regelt de Statamic 6-compatibiliteit,
maar is nog niet gemerged en de addon wordt nauwelijks onderhouden — dus die
wijzigingen nemen we zelf over in de fork.

### Volgorde bij de upgrade
1. JB voert de Statamic 6-upgrade van de hoofdapp uit.
2. PR #40-wijzigingen overnemen in deze fork → schone Statamic 6-basis.
3. Daarna pas: localization-endpoints (TODO 1) en eventueel `force=true`
   (TODO 2) toevoegen, bovenop die schone basis.

### Kernwijzigingen uit PR #40 (ter referentie)
- `composer.json`: `statamic/cms` van `^4.0 || ^5.0` → `^6.0`; testbench,
  pest en larastan naar hun v6-compatibele versies.
- Nieuwe file `src/Http/Controllers/ApiController.php` (eigen base controller;
  Statamic 6 heeft `Statamic\Http\Controllers\API\ApiController` verplaatst).
- Alle controllers: `use Statamic\Http\Controllers\API\ApiController;` →
  `use Tv2regionerne\StatamicPrivateApi\Http\Controllers\ApiController;`,
  plus kleine aanpassingen (Carbon imports, mimetype guessing).
- Volledige diff:
  https://patch-diff.githubusercontent.com/raw/tv2regionerne/statamic-private-api/pull/40.diff

### Aandachtspunten bij het samenvoegen
- De DELETE-fix (in `AssetsController.php`) en de localization-controllers
  zijn losse stukken; alleen `routes/api.php` en `AssetsController.php` worden
  door zowel PR #40 als onze eigen wijzigingen geraakt. Daar even opletten.
- Pas de `composer.json` van de hoofdapp NIET naar Statamic 6 laten wijzen
  zolang productie nog op Statamic 5 draait.

### Uitzondering op de volgorde
Als de Statamic 6-upgrade nog maanden weg is, kan TODO 1 (localizations)
eerder toegevoegd worden — je hebt er dagelijks profijt van (geen handmatig
talen aanmaken meer). Kostprijs: de bestanden ná de Statamic 6-merge één keer
opnieuw controleren.

---

## Referenties
- Upstream PR #40: https://github.com/tv2regionerne/statamic-private-api/pull/40
- Statamic 5→6 upgrade guide: https://statamic.dev/upgrade-guide/5-to-6
