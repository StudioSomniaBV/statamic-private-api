# TODO / status — statamic-private-api fork

E�n bron van waarheid voor deze fork. Vervangt eerdere losse TODO-bestanden.

Deze fork is een kopie van tv2regionerne/statamic-private-api met eigen
aanpassingen. De package-naam in `composer.json` is BEWUST hetzelfde gebleven
(`tv2regionerne/statamic-private-api`), zodat de hoofdapp alleen een
`repositories`-entry naar deze fork nodig heeft.

## Branch- en versie-strategie

- `main`  = Statamic 5-versie. Bevat DELETE-fix + localization-endpoints.
            Dit is de versie die nu live hoort te draaien.
- `statamic-6` = Statamic 6-versie. Zelfde eigen fixes, plus PR #40
            (Statamic 6-compatibiliteit) erbovenop. KLAARGEZET, nog ongetest.
- Releases worden getagd; JB pint op een vaste tag, NIET op dev-main.
  (Afgesproken met JB i.v.m. hun PR/review-proces — zo komt er nooit
  automatisch een commit van ons live.)
  - `v1.0.0` = huidige main (Statamic 5). Getagd.
  - `v2.0.0` = wordt de statamic-6 branch, te taggen bij de upgrade.

---

## ✅ Al doorgevoerd

### DELETE assets fix (main + statamic-6, getest op S5)
`AssetsController::destroy()` gebruikt nu `$this->authorize('delete', $asset);`
in plaats van het originele `$this->authorize('delete', [AssetContract::class,
$container])`. De originele variant crashte met een 500 (ArgumentCountError in
Statamic's `AssetPolicy`). Getest: POST→200, GET→200, DELETE→204, GET→404.

> Staat LOS van PR #40. PR #40 gaat enkel over Statamic 6, niet over deze fix.

### Localization-endpoints (main + statamic-6, NOG TE TESTEN)
Toegevoegd: 3 controllers + routes in `routes/api.php`:
- `SitesController` → `GET /sites`
- `EntryLocalizationsController` → GET/POST/DELETE op
  `/collections/{c}/entries/{entry}/localizations`
- `TermLocalizationsController` → GET/POST/DELETE op
  `/taxonomies/{t}/terms/{term}/localizations`

Doel: de sync-tool kan zelf taalversies van entries én terms aanmaken via de
API. Voorheen handmatig in de CP nodig. Endpoints gebruiken
`makeLocalization()` zodat de origin-chain intact blijft (voorkomt de eerdere
PT `filter[cities]` bug).

> NOG NIET GETEST tegen de live server. Zie "Nog te doen" punt 1.

### Statamic 6-branch klaargezet (statamic-6, NOG TE TESTEN)
PR #40 volledig overgenomen op de `statamic-6` branch, bovenop onze eigen
fixes:
- Nieuwe `src/Http/Controllers/ApiController.php` (lokale base controller;
  vangt Statamic 6's hernoemde `filterSortAndPaginate` op).
- 9 controllers: `use Statamic\Http\Controllers\API\ApiController;` verwijderd.
- 4 controllers herschreven: CollectionEntries (date-handling), Users (geen
  CpController meer), GlobalVariables (`save()` ipv addLocalization), Navs
  (eigen destroy).
- `AssetsController`: use-regel weg + `new MimeTypes` (DELETE-fix behouden).
- `composer.json`: statamic/cms ^6.0, testbench ^10.0, pest ^3.0,
  larastan ^3.0, pest-plugin-watch verwijderd.
- Localization-bestanden ongewijzigd (werken op S6 zoals ze zijn).

> NOG NIET GETEST — kan pas tegen een echte Statamic 6-omgeving (bij de
> upgrade). `v1.0.0` blijft de terugval als de S6-branch niet meteen werkt.

---

## ⬜ Nog te doen

### 1. Localization-endpoints testen (BLOKKEERT verder werk)
Voorwaarde: JB haakt de fork aan in composer.json en deployt.
Daarna: `statamic-crud-tester` edge function draaien — POST/GET/DELETE op een
test-entry ÉN een test-term in een niet-default site (bv. `pt`). Let extra op
de term-endpoints (Statamic's term-localization methodes zijn versiegevoelig).
Pas als dit groen is, kan het CRUD-uitbouwplan in de tool erop bouwen.

### 2. Statamic 6-upgrade afronden (gepland: ~volgende week)
Wanneer JB de hoofdapp naar Statamic 6 upgradet:
1. Tag de `statamic-6` branch als `v2.0.0`.
2. JB pint de hoofdapp-composer.json op `v2.0.0`.
3. Testen tegen de nieuwe S6-omgeving: localization-endpoints, DELETE-fix,
   en de normale entry/term-flows.
4. Bij problemen: terug naar `v1.0.0` (= S5) is altijd mogelijk.

### 3. (Geparkeerd) `?force=true` upsert op asset-upload
Re-publishes kosten nu 2 calls (POST→422→PATCH). Een `?force=true` op
`AssetsController::store()` brengt dat naar 1 call (~350 calls minder per
tour-republish bij 50 assets × 7 talen).

Implementeer als **delete + store**, NIET via `update()`: `update()` vervangt
alleen metadata, niet de bytes — dat zou stille datacorruptie geven. In het
force-pad de `delete`-permissie apart autoriseren naast `store`.

Geen bug, puur optimalisatie. Client-side fallback werkt en is getest. Pas
oppakken na de Statamic 6-upgrade, op beide branches gelijk houden.

---

## Referenties
- Upstream PR #40: https://github.com/tv2regionerne/statamic-private-api/pull/40
- Statamic 5→6 upgrade guide: https://statamic.dev/upgrade-guide/5-to-6
