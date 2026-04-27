# Field Configuration Guide

`src/backend/config.php` is **the** single source of truth.
Columns, filters, dropdowns, row actions and diagnostics all come from one
file. The frontend derives everything from the `init.php` response — there
is no parallel frontend registry to keep in sync.

> Loading flow: `config.php` → `ConfigNormalizer::normalize()` → `ConfigValidator::validate()` → `init.php` / `query.php`.
> The normalizer expands the compact form into the verbose internal shape, so the rest of the codebase only ever sees the canonical schema.

---

## Table of Contents

- [Compact field syntax (start here)](#compact-field-syntax-start-here)
- [Filter shorthands](#filter-shorthands)
- [Auto-derived field defaults](#auto-derived-field-defaults)
- [Advanced / overrides — top-level config keys](#advanced--overrides--top-level-config-keys)
- [Verbose `FIELD_MAP` form (escape hatch)](#verbose-field_map-form-escape-hatch)
- [Special filters (handler-based)](#special-filters-handler-based)
- [Row actions](#row-actions)
- [Computed fields](#computed-fields)
- [Validator rules](#validator-rules)
- [Common errors](#common-errors)

---

## Compact field syntax (start here)

Each entry in `$CONFIG['FIELD_MAP']` is an associative array. Most fields only
need 4 keys; the normalizer fills in everything else.

```php
$CONFIG['FIELD_MAP'] = [
  ['id' => 'actions', 'type' => 'actions'],
  ['id' => 'incident',     'label' => 'Vorgangsnummer', 'dbColumn' => 'incident',          'filter' => 'text'],
  ['id' => 'entryDate',    'label' => 'Eingangsdatum',  'dbColumn' => 'eingangsdatum',     'type'   => 'date'],
  ['id' => 'invoiceDate',  'label' => 'Rechnungsdatum', 'dbColumn' => 'rechnungsdatum',    'type'   => 'date',     'filter' => 'daterange'],
  ['id' => 'grossAmount',  'label' => 'Bruttobetrag',   'dbColumn' => 'bruttobetrag',      'type'   => 'currency', 'filter' => 'numberrange'],
  ['id' => 'stepLabel',    'label' => 'Schritt',        'dbColumn' => 'steplabel',         'filter' => 'autocomplete'],
];
```

---

## Filter shorthands

The `'filter'` key accepts a string instead of a full filter array.

| Shorthand        | Frontend behaviour             | SQL emitted                             | Notes                                                                                                             |
| ---------------- | ------------------------------ | --------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `'text'`         | Free-text input                | `LOWER(col) LIKE '%val%'`               | Case-insensitive substring match                                                                                  |
| `'date'`         | Single date picker             | `col >= 'val'`                          | Use `'daterange'` for from/to                                                                                     |
| `'daterange'`    | Two date pickers (from / to)   | `col >= 'from' AND col <= 'to'`         | Range ids derived as `{id}From` / `{id}To`                                                                        |
| `'numberrange'`  | Two number inputs (from / to)  | `col >= from AND col <= to`             |                                                                                                                   |
| `'dropdown'`     | Single-select dropdown         | `col = 'val'`                           | Auto-references `STATIC_DROPDOWNS[id]` or `DROPDOWN_SOURCES[id]`                                                  |
| `'autocomplete'` | Multi-select chip autocomplete | `valueCol IN (…)`                       | `valueCol` is taken from the matching `DROPDOWN_SOURCES[id]`; falls back to `dbColumn` if no source is registered |
| `'boolean'`      | Ja / Nein dropdown             | `col = 1` or `(col = 0 OR col IS NULL)` | Auto-displays `Ja` / `Nein` in the cell                                                                           |

For dropdown / autocomplete: register the option list under the same key as
the field's `id` in `DROPDOWN_SOURCES` (DB-queried) or `STATIC_DROPDOWNS`
(hard-coded). When neither is supplied for a `status`-typed field or for
`laufzeit`, the normalizer auto-injects sensible defaults.

---

## Auto-derived field defaults

When a field key is omitted, the normalizer fills it in:

| Key     | Default                                                           |
| ------- | ----------------------------------------------------------------- |
| `id`    | `camelCase(dbColumn)` — e.g. `'DW_DokumentID'` → `'dwDokumentID'` |
| `label` | the resolved `id`                                                 |
| `type`  | `'text'`                                                          |
| `align` | `'left'`                                                          |

So the absolute minimum is:

```php
['dbColumn' => 'Mandant'],                       // shows column "mandant" with no filter
['dbColumn' => 'Mandant', 'filter' => 'text'],   // …with a free-text filter
```

---

## Advanced / overrides — top-level config keys

Every key in the table below has a sane default supplied by
`ConfigNormalizer::DEFAULTS`. Set them in `config.php` only when you need to
override the default.

| Key                 | Default                                                                      | What it does                                                                                   |
| ------------------- | ---------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | ----------------------------------------- |
| `DB_TYPE`           | `'auto'`                                                                     | `'mssql'`, `'mysql'` or `'auto'` (detected via JobRouter)                                      |
| `PREFERENCES_TABLE` | `'WIDGET_SIMPTRACK'`                                                         | Table for per-user preferences (auto-created on first run)                                     |
| `ROW_AUTH`          | `['mode' => 'none']`                                                         | Row-level access filter — see below                                                            |
| `THEME`             | `['primaryColor' => '#ffcc00', 'defaultMode' => 'dark']`                     | Brand color + initial dark/light mode                                                          |
| `LOCALE`            | `['language' => 'de-DE', 'currency' => 'EUR']`                               | Date / number formatting                                                                       |
| `CACHE_TTL`         | `600`                                                                        | Seconds the dropdown-options cache is kept                                                     |
| `STATUS_COLUMN`     | `''` (status feature disabled)                                               | DB column carrying `'completed'` / `'rest'` for the status badge                               |
| `ESCALATION_COLUMN` | `''`                                                                         | Date column used to decide _fällig_ vs _nicht fällig_ for active rows                          |
| `STATUS_LABELS`     | `['completed' => 'Beendet', 'due' => 'Fällig', 'not_due' => 'Nicht fällig']` | German labels emitted by `mapRow()` for the status column                                      |
| `COMPUTED_FIELDS`   | `[]`                                                                         | Server-side custom value mappings — see [Computed fields](#computed-fields)                    |
| `DROPDOWN_SOURCES`  | `[]`                                                                         | DB-queried option lists — keys must match the field id of the consuming filter                 |
| `STATIC_DROPDOWNS`  | `[]`                                                                         | Hard-coded option lists — `status` and `laufzeit` are auto-injected when the widget needs them |
| `SPECIAL_FILTERS`   | `[]`                                                                         | Custom-handler filters not bound to a single column — see below                                |
| `LAUFZEIT_COLUMN`   | unset (filter disabled)                                                      | DB column whose age (in days) drives `LAUFZEIT_RANGES`                                         |
| `LAUFZEIT_RANGES`   | `[]`                                                                         | `[label => [minDays, maxDays                                                                   | null]]` buckets for the laufzeit dropdown |
| `COOR_COLUMN`       | unset (filter disabled)                                                      | Customer-specific bool column for the legacy "Coor" Ja/Nein filter                             |
| `LOG_DIR`           | `src/backend/logs/`                                                          | Where `Logger` writes its JSON-line files                                                      |
| `DEBUG_LOG`         | `false`                                                                      | When `true`, every WHERE-built SQL is logged                                                   |
| `SPV_FILTER`        | unset                                                                        | Optional autocomplete-marker config (used by `simplifytable`, not Simptrack)                   |

### `ROW_AUTH` — row-level access

```php
$CONFIG['ROW_AUTH'] = ['mode' => 'none'];                              // open access (default)
$CONFIG['ROW_AUTH'] = ['mode' => 'equals',        'column' => 'owner'];
$CONFIG['ROW_AUTH'] = ['mode' => 'list_contains', 'column' => 'berechtigung'];   // legacy AUTH_COLUMN behaviour
$CONFIG['ROW_AUTH'] = ['mode' => 'custom',        'sql' => "EXISTS (SELECT 1 FROM ACL a WHERE a.id = t.id AND a.user = '{user}')"];
```

The placeholder `{user}` is replaced with the (escaped) JobRouter username.

### `LAUFZEIT_RANGES`

When `LAUFZEIT_COLUMN` is set, the normalizer auto-fills
`STATIC_DROPDOWNS['laufzeit']` from these range labels:

```php
$CONFIG['LAUFZEIT_COLUMN'] = 'indate';
$CONFIG['LAUFZEIT_RANGES'] = [
  '0-5 Tage'   => [0, 5],
  '6-10 Tage'  => [6, 10],
  '11-20 Tage' => [11, 20],
  '21+ Tage'   => [21, null],
];
$CONFIG['SPECIAL_FILTERS'] = [
  ['id' => 'laufzeit', 'label' => 'Laufzeit', 'type' => 'dropdown',
   'key' => 'laufzeit', 'defaultValue' => 'all', 'optionsKey' => 'laufzeit',
   'handler' => 'laufzeit'],
];
```

---

## Verbose `FIELD_MAP` form (escape hatch)

The compact `'filter' => 'text'` shorthand expands into a full filter array.
You can still write that array yourself — e.g. when the `key` must differ from
the field `id`, when the autocomplete pulls from a non-default source, or when
the `dbColumn` of the filter differs from the displayed `dbColumn`.

```php
[
  'id'       => 'priority',
  'label'    => 'Priorität',
  'type'     => 'text',
  'align'    => 'center',
  'dbColumn' => 'priority',
  'filter'   => [
    'type'         => 'dropdown',
    'key'          => 'priority',
    'dbColumn'     => 'priority',
    'dbType'       => 'equality',
    'defaultValue' => 'all',
    'optionsKey'   => 'priority',
  ],
],
```

### `filter.dbType` reference

| `dbType`     | SQL emitted                          | Use for                                     |
| ------------ | ------------------------------------ | ------------------------------------------- |
| `text_like`  | `LOWER(col) LIKE '%val%'`            | Free-text search, partial match             |
| `equality`   | `col = 'val'`                        | Exact match dropdown                        |
| `number_gte` | `col >= val`                         | Range-from / minimum                        |
| `number_lte` | `col <= val`                         | Range-to / maximum                          |
| `date_gte`   | `col >= 'val'`                       | Date range from                             |
| `date_lte`   | `col <= 'val'`                       | Date range to                               |
| `boolean_10` | `col = 1` or `(col = 0 OR col NULL)` | Ja/Nein mapped to 1/0; auto Ja/Nein display |

Range filters need both halves:

```php
'filter' => [
  'type'         => 'numberrange',
  'key'          => 'bruttobetrag',
  'dbColumn'     => 'bruttobetrag',
  'defaultValue' => '',
  'rangeIds'     => ['bruttobetragFrom', 'bruttobetragTo'],
  'rangeDbTypes' => ['number_gte',       'number_lte'],
],
```

Multi-select autocomplete:

```php
'filter' => [
  'type'         => 'autocomplete',
  'key'          => 'gesellschaft',
  'defaultValue' => [],
  'optionsKey'   => 'gesellschaft',
  'listFilter'   => ['mandantnr', false],   // [dbColumn, castToInt]
],
```

---

## Special filters (handler-based)

`SPECIAL_FILTERS` carries filters whose WHERE clause is too custom to express
through `dbType`. Currently implemented handlers: `laufzeit`, `coor`.

To add a new handler:

1. Add an entry under `$CONFIG['SPECIAL_FILTERS']` with a unique `handler` name.
2. Implement the WHERE branch in `query.php::buildWhereClauses()`.
3. Add the handler name to `ConfigValidator::KNOWN_HANDLERS`.

Otherwise the validator refuses to start with rule **R4**.

---

## Row actions

Every entry in `$CONFIG['ROW_ACTIONS']` carries a `urlTemplate`. Allowed
placeholders:

- `{BASE_URL}` — the configured base URL
- Any `id` from `FIELD_MAP`
- Per-row tokens: `{processid}`, `{key}`, `{username}`, `{documentId}`, `{incident}`

Unknown placeholders → validator rule **R3** (HTTP 500, red banner).
`'target' => 'popup'` opens a sized `window.open` popup; `'_blank'` opens a
new tab.

---

## Computed fields

`boolean_10` columns are auto-mapped to `Ja` / `Nein` in `mapRow()`.
`COMPUTED_FIELDS` is reserved for **non-boolean** custom mappings.

Example — map `1 → 'Rot'`, `2 → 'Gelb'`, `3 → 'Grün'`:

```php
$CONFIG['COMPUTED_FIELDS'] = [
  'ampel' => [
    'source'  => 'ampel',
    'mapping' => [1 => 'Rot', 2 => 'Gelb', 3 => 'Grün', null => ''],
  ],
];
```

---

## Validator rules

`ConfigValidator` runs at the top of every `init.php` / `query.php` request
(after the normalizer). Failures return HTTP 500 with actionable JSON which
the red in-widget banner surfaces:

```json
{
  "error": "Field 'priority' filter.optionsKey 'priority' is not defined …",
  "rule": "R1",
  "hint": "Add STATIC_DROPDOWNS['priority'] = […] or DROPDOWN_SOURCES['priority'] = […].",
  "details": { "availableStatic": [...], "availableSources": [...] }
}
```

| Rule | Check                                                                                       |
| ---- | ------------------------------------------------------------------------------------------- |
| R1   | Every dropdown / autocomplete filter has a resolvable `optionsKey`                          |
| R2   | Every `FIELD_MAP` `dbColumn` exists in `DATA_VIEW` (cached 10 min via `INFORMATION_SCHEMA`) |
| R3   | Every `ROW_ACTIONS.urlTemplate` placeholder is a known token                                |
| R4   | Every `SPECIAL_FILTERS.handler` is implemented                                              |
| R5   | Every `filter.listFilter[0]` references a real `DATA_VIEW` column                           |
| R6   | `SPV_FILTER` (when set) references a real `DROPDOWN_SOURCES` entry                          |
| R7   | `ROW_AUTH` mode and required keys are valid                                                 |

---

## Common errors

| Symptom                                            | Likely rule | Fix                                                                                         |
| -------------------------------------------------- | ----------- | ------------------------------------------------------------------------------------------- |
| Dropdown shows "no entries"                        | R1          | Add the option list under the field's `id` in `STATIC_DROPDOWNS` or `DROPDOWN_SOURCES`      |
| Red banner: `column '…' does not exist`            | R2          | Typo in `dbColumn` / `filter.dbColumn` / `listFilter[0]`. The banner lists the real columns |
| Row action URL has empty placeholders              | R3          | Use a token from [Row actions](#row-actions) or a `FIELD_MAP` id                            |
| `Tracking_ShowTracking` returns ZUGRIFF VERWEIGERT | (config)    | `TRACKING_PASSPHRASE` does not match the customer's JobRouter tracking passphrase           |
| Most cells render `-` even though the DB has data  | (config)    | A `dbColumn` is misspelled — case is normalized but spelling is not                         |

---

## File reference

- `src/backend/config.php` — the source of truth
- `src/backend/ConfigNormalizer.php` — compact-form expansion + defaults
- `src/backend/ConfigValidator.php` — validation rules
- `src/backend/Logger.php` — file logger (daily rotation)
- `src/backend/init.php` — emits schema to frontend (columns + standaloneFilters + dropdownOptions + rowActions)
- `src/backend/query.php` — builds SQL from config; emits `processid`, `key`, auto Ja/Nein
- `src/lib/schema.ts` — derives `FilterConfig[]`, default filter values, URL params from the server payload

No manual frontend edits are needed to add or change a field.
