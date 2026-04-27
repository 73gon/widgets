# Field Configuration Guide

**`src/backend/config.php` is THE single source of truth.**
Columns, filters, dropdowns, row actions and diagnostics all come from one file.
The frontend derives everything from the `init.php` response — there is no
parallel frontend registry to keep in sync.

## Table of Contents

- [Quick Start: Adding a Field](#quick-start-adding-a-field)
- [Filter Types](#filter-types)
- [Special Filters (Handler-based)](#special-filters-handler-based)
- [Multi-Select (IN-List) Filters](#multi-select-in-list-filters)
- [Row Actions](#row-actions)
- [Computed Fields](#computed-fields)
- [Diagnostics — Validator & Logger](#diagnostics--validator--logger)
- [Common Errors](#common-errors)

---

## Quick Start: Adding a Field

### Example 1 — Column with dropdown filter

Add **one** entry to `$CONFIG['FIELD_MAP']` in `src/backend/config.php`:

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

If the dropdown options are static, add them to `$CONFIG['STATIC_DROPDOWNS']`:

```php
'priority' => [
  ['id' => 'Hoch',    'label' => 'Hoch'],
  ['id' => 'Mittel',  'label' => 'Mittel'],
  ['id' => 'Niedrig', 'label' => 'Niedrig'],
],
```

…or to `$CONFIG['DROPDOWN_SOURCES']` for DB-backed options.

**That's it.** No frontend changes, no other backend files.

### Example 2 — Boolean Ja/Nein column

```php
[
  'id'       => 'coorspvflag',
  'label'    => 'Coor SPV',
  'type'     => 'text',
  'align'    => 'center',
  'dbColumn' => 'coorspvflag',
  'filter'   => [
    'type'         => 'dropdown',
    'key'          => 'coorspvflag',
    'dbColumn'     => 'coorspvflag',
    'dbType'       => 'boolean_10',
    'defaultValue' => 'all',
    'optionsKey'   => 'coorspvflag',
  ],
],
```

Add `coorspvflag` to `STATIC_DROPDOWNS` with `Ja`/`Nein` options.
`dbType: 'boolean_10'` auto-maps `1 → 'Ja'` and `0/null → 'Nein'` in `mapRow()`
— no `COMPUTED_FIELDS` entry needed.

### Example 3 — Column without a filter

Drop the `filter` key:

```php
['id' => 'paymentDate', 'label' => 'Zahldatum', 'type' => 'date', 'align' => 'left', 'dbColumn' => 'zahldatum'],
```

---

## Filter Types

Set `filter.dbType` to tell `query.php` how to build the WHERE clause.

| `dbType`     | SQL emitted                          | Use for                                     |
| ------------ | ------------------------------------ | ------------------------------------------- |
| `text_like`  | `LOWER(col) LIKE '%val%'`            | Free-text search, partial match             |
| `equality`   | `col = 'val'`                        | Exact match dropdown                        |
| `number_gte` | `col >= val`                         | Number range from / minimum                 |
| `number_lte` | `col <= val`                         | Number range to / maximum                   |
| `date_gte`   | `col >= 'val'`                       | Date range from                             |
| `date_lte`   | `col <= 'val'`                       | Date range to                               |
| `boolean_10` | `col = 1` or `(col = 0 OR col NULL)` | Ja/Nein mapped to 1/0; auto Ja/Nein display |

For range filters (number/date), use:

```php
'filter' => [
  'type'          => 'numberrange',      // or 'daterange'
  'key'           => 'bruttobetrag',
  'dbColumn'      => 'bruttobetrag',
  'defaultValue'  => '',
  'rangeIds'      => ['bruttobetragFrom', 'bruttobetragTo'],
  'rangeDbTypes'  => ['number_gte',       'number_lte'],
],
```

---

## Special Filters (Handler-based)

`SPECIAL_FILTERS` is for filters whose WHERE clause is too complex to express
via `dbType`. Currently implemented handlers: `laufzeit`, `coor`.

To add a new handler:

1. Add an entry under `$CONFIG['SPECIAL_FILTERS']` with a unique `handler` name.
2. Implement the WHERE branch in `query.php::buildWhereClauses()`.
3. Add the handler name to `ConfigValidator::KNOWN_HANDLERS`.

Otherwise the validator will refuse to start with rule **R4**.

---

## Multi-Select (IN-List) Filters

For autocomplete multi-select (Gesellschaft, Fonds, Schritt):

```php
'filter' => [
  'type'         => 'autocomplete',
  'key'          => 'gesellschaft',
  'defaultValue' => [],
  'optionsKey'   => 'gesellschaft',
  'listFilter'   => ['mandantnr', false],  // [dbColumn, castToInt]
],
```

Set the second element to `true` to cast IN-values to `int` (e.g. `step`).

---

## Row Actions

Every action in `$CONFIG['ROW_ACTIONS']` has a `urlTemplate`. Allowed placeholders:

- `{BASE_URL}` — config base URL
- Any `id` from `FIELD_MAP`
- Emitted-per-row tokens: `{processid}`, `{key}`, `{username}`, `{documentId}`, `{incident}`

Unknown placeholders → validator rule **R3** (HTTP 500, red banner).

`target: 'popup'` opens in a sized `window.open` popup; `_blank` in a new tab.

---

## Computed Fields

Booleans (`filter.dbType: 'boolean_10'`) are auto-mapped to `Ja`/`Nein` in
`mapRow()`. `$CONFIG['COMPUTED_FIELDS']` is reserved for **non-boolean** custom
mappings (currently empty).

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

## Diagnostics — Validator & Logger

**`ConfigValidator`** runs at the top of every `init.php` / `query.php`
request. Failures return HTTP 500 with actionable JSON:

```json
{
  "error": "Field 'priority' filter.optionsKey 'priority' is not defined ...",
  "rule": "R1",
  "hint": "Add STATIC_DROPDOWNS['priority'] = [...] or DROPDOWN_SOURCES['priority'] = [...].",
  "details": { "availableStatic": [...], "availableSources": [...] }
}
```

The red banner in the UI surfaces this instantly.

**Validator rules:**

| Rule | Check                                                                                       |
| ---- | ------------------------------------------------------------------------------------------- |
| R1   | Every dropdown/autocomplete filter has a resolvable `optionsKey`                            |
| R2   | Every `FIELD_MAP` `dbColumn` exists in `DATA_VIEW` (cached 10 min via `INFORMATION_SCHEMA`) |
| R3   | Every `ROW_ACTIONS.urlTemplate` placeholder is a known token                                |
| R4   | Every `SPECIAL_FILTERS.handler` is implemented                                              |
| R5   | Every `filter.listFilter[0]` references a real `DATA_VIEW` column                           |

**`Logger`** writes JSON-line logs to `$CONFIG['LOG_DIR']`
(default `src/backend/logs/`). Daily rotation, 14-day retention, 5 MB per file.

Enable `$CONFIG['DEBUG_LOG'] = true;` to log the final SQL of every request.

---

## Common Errors

| Symptom                                   | Likely rule | Fix                                                                                    |
| ----------------------------------------- | ----------- | -------------------------------------------------------------------------------------- |
| Dropdown shows "no entries"               | R1          | Add the `optionsKey` to `STATIC_DROPDOWNS` or `DROPDOWN_SOURCES`.                      |
| Red banner: `column '...' does not exist` | R2          | Typo in `dbColumn` / `filter.dbColumn` / `listFilter[0]`. Banner lists actual columns. |
| Row action URL has empty placeholders     | R3          | Use a valid placeholder (see [Row Actions](#row-actions)).                             |
| Validator says handler not implemented    | R4          | Implement in `query.php::buildWhereClauses()` + add to `KNOWN_HANDLERS`.               |
| Boolean column shows `0` / `1`            | (config)    | Set `filter.dbType: 'boolean_10'`.                                                     |

---

## File Reference

- `src/backend/config.php` — the source of truth
- `src/backend/ConfigValidator.php` — validation rules
- `src/backend/Logger.php` — file logger (daily rotation)
- `src/backend/init.php` — emits schema to frontend (columns + standaloneFilters + dropdownOptions + rowActions)
- `src/backend/query.php` — builds SQL from config; emits `processid`, `key`, auto Ja/Nein
- `src/lib/schema.ts` — derives `FilterConfig[]`, default filter values, URL params from server payload

No manual frontend edits are needed to add or change a field.
