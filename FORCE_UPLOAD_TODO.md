# TODO: `?force=true` voor asset upload (upsert)

## Context
Bij re-publishes van een tour slaan we ~50 assets × 7 talen op in Statamic via de private API.
De huidige flow is **twee calls**:
1. `POST /assets` → 422 "A file already exists"
2. `PATCH /assets/{id}` → 200

Dit is onnodig traag en genereert transient errors.

## Voorstel
Voeg een `?force=true` (of `?upsert=true`) query parameter toe aan `POST /api/private/asset-containers/{container}/assets`.

- **Zonder `force=true`**: onveranderd — 422 bij duplicate.
- **Met `force=true`**: als het bestand al bestaat, voer intern uit:
  1. Soft-delete het oude asset (zoals `delete()` doet).
  2. Sla het nieuwe bestand op met `store()` (zoals een nieuwe upload).
  3. Retourneer 200.

## Belangrijk: niet route naar `update()`
`PATCH /assets/{id}` roept de CP `AssetsController::update` aan, die alleen **metadata/blueprint velden** vervangt, **niet de binaire bytes**.
Als we `force=true` zouden routeren naar `update()`, krijgen we stille datacorruptie: 200 OK terwijl de oude MP3 blijft staan.
De correcte implementatie is dus **delete + store**, niet route-naar-update.

## Autorisatie
In het `force=true` pad moet de `update` permissie apart geautoriseerd worden (naast `store`).

## Timing
**Parkeer dit tot na de Statamic 6-upgrade.**
- PR #40 (Asset Policy fix) is al gemerged in onze fork.
- Bij de Statamic 6-upgrade moeten we de fork opnieuw verzoenen met upstream.
- Deze wijziging bovenop een schone basis toevoegen voorkomt dubbel merge-werk.

## Winst
~2× snellere re-publishes. Bij 50 assets × 7 talen = 350 uploads halveer je van 700 calls naar 350 calls.

## Huidige workaround
Client-side fallback blijft actief: POST → 422 → PATCH (metadata) + DELETE/POST (nieuwe versie indien nodig). Deze workaround is getest en stabiel.
