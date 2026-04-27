# Simptrack Widget

Generic, customer-neutral starter version of the SimplifyTable widget.
Used for prospect demos and as the baseline when onboarding new customers.

It ships the full feature set (filtering, sorting, pagination, user
preferences, row actions, status badges, autocomplete dropdowns, …) but
without any customer-specific columns, branding, or business logic. To turn
Simptrack into a customer-specific widget, copy it, rename it, and edit
[src/backend/config.php](src/backend/config.php).

---

## Architecture in one paragraph

`config.php` is the single source of truth. `init.php` ships the schema
(columns, filters, dropdown options, theme, locale, row actions) to the
frontend. `query.php` builds the SQL from the same config.
`ConfigValidator.php` validates the schema on every `init` request (rules
R1–R6) and returns a machine-readable error if something is wrong. The React
frontend is a thin consumer — it does not hardcode column names, filter
keys, or labels.

---

## Config reference

All blocks live in [src/backend/config.php](src/backend/config.php). Each
block is described below in the order it appears in the file.

### 1. Database

| Key                 | Purpose                                                             |
| ------------------- | ------------------------------------------------------------------- |
| `DB_TYPE`           | `'mssql'`, `'mysql'`, or `'auto'` (auto-detect from JobRouter).     |
| `DATA_VIEW`         | Main SQL view / table queried by the widget.                        |
| `PREFERENCES_TABLE` | Table that stores per-user preferences. Auto-created on first run.  |
| `AUTH_COLUMN`       | Column holding the comma-separated access list used to filter rows. |

### 2. URLs & Secrets

| Key                   | Purpose                                                          |
| --------------------- | ---------------------------------------------------------------- |
| `BASE_URL`            | Base URL of the customer's JobRouter (no trailing slash).        |
| `TRACKING_PASSPHRASE` | Secret used to generate the MD5 tracking key for incident links. |

### 3. Theme

| Key           | Purpose                                                              |
| ------------- | -------------------------------------------------------------------- |
| `defaultMode` | `'light'` or `'dark'` — the widget's default appearance.             |
| `primary`     | Primary CSS color (used for highlights, the active scrollbar, etc.). |
| `accent`      | Accent color used for badges and secondary highlights.               |

### 4. `FIELD_MAP` (columns + filters)

An array of column definitions. Each entry can declare a `filter` block; if
present, the column also acts as a filter in the filter bar.

Per entry:

- `id` — frontend identifier.
- `label` — header label shown in the table.
- `type` — `'text'`, `'number'`, `'date'`, `'status'`, `'action'`.
- `align` — `'left'` | `'center'` | `'right'`.
- `dbColumn` — name in `DATA_VIEW`.
- `filter` — optional. Subkeys:
  - `id`, `label`, `key` — frontend identifiers.
  - `type` — `'text'`, `'dropdown'`, `'autocomplete'`, `'daterange'`, `'numberrange'`.
  - `dbColumn`, `dbType` — SQL column and predicate type (`'text_like'`, `'in'`, `'between'`, …).
  - `defaultValue` — initial value.
  - `optionsKey` — for dropdown/autocomplete: which `DROPDOWN_SOURCES` /
    `STATIC_DROPDOWNS` entry to load.
  - `listFilter` — optional `[column, distinct]` used to scope autocomplete.

### 5. `SPECIAL_FILTERS`

Standalone filters that aren't tied to a single column (status grouping,
duration buckets, etc.). Same shape as `FIELD_MAP[].filter`.

### 6. `DROPDOWN_SOURCES`

DB-queried options. One entry per logical dropdown:

```php
'schritt' => [
  'table'    => 'DATA_VIEW',     // or a literal table name
  'valueCol' => 'step',
  'labelCol' => 'steplabel',
  'distinct' => true,
  'orderBy'  => 'step',          // optional
],
```

### 7. `STATIC_DROPDOWNS`

Hardcoded option lists keyed identically to `DROPDOWN_SOURCES`. Used for
status, priority, and any other enum that doesn't live in the database.

### 8. `ROW_ACTIONS`

Buttons rendered in the action column per row. Each entry declares an icon,
label, URL template (with `{column}` placeholders), and optional permission
hooks.

### 9. `STATUS_MAP`

Maps raw `status` values from the DB to a display label and a color token.
Drives the colored status badges.

### 10. `CACHE`

| Key                  | Purpose                                       |
| -------------------- | --------------------------------------------- |
| `dropdownTtlSeconds` | How long dropdown options are cached on disk. |
| `enabled`            | Master switch.                                |

### 11. `LOCALE`

Date/number formatting locale (`'de-DE'`, `'en-US'`, …) and the timezone.

### 12. `COMPUTED_FIELDS` (optional)

Server-side derived columns (e.g. `laufzeit` computed from two timestamps).

### 13. `DIAGNOSTICS` (optional)

Toggles verbose logging in `init.php` / `query.php` for support sessions.

### 14. `OPTION_MARKERS` (optional, not present in Simptrack)

Per-option tags on autocomplete dropdowns (e.g. SPVs, Konzerngesellschaften).
Not used in the generic Simptrack template — see the `simplifytable` widget
for an example.

---

## First-time deployment to a new customer

1. **Get the build.** Download the latest release zip from GitHub Releases
   (`simptrack-vX.Y.Z.zip`). It contains the compiled `dist/` plus the PHP
   backend.
2. **Drop it into JobRouter.** Extract to
   `<jobrouter>/dashboard/MyWidgets/Simptrack/`. (Rename the folder if the
   customer needs a custom widget name.)
3. **Edit `src/backend/config.php`.** At minimum change:
   - `DB_TYPE`, `DATA_VIEW`, `AUTH_COLUMN`
   - `BASE_URL`, `TRACKING_PASSPHRASE`
   - `FIELD_MAP` columns to match the customer's view
   - `THEME` colors to match the customer's branding
4. **Smoke-test in JobRouter.** Open the dashboard. If validation fails, the
   widget shows a clear `ConfigValidationException` with the exact rule and
   key that broke.
5. **(Optional) Adjust `STATUS_MAP`, `ROW_ACTIONS`, `STATIC_DROPDOWNS`** to
   match the customer's process.

---

## Updating to a new release

> **Critical: do not overwrite `src/backend/config.php` blindly.** It contains
> per-customer values (DATA_VIEW, BASE_URL, secrets, FIELD_MAP).

Recommended workflow:

1. Back up the current `src/backend/config.php` and any
   `src/backend/cache/` contents you want to keep.
2. Download the new release zip from GitHub Releases.
3. Extract it over the existing widget folder.
4. **Restore the backed-up `config.php`** (or merge new optional keys from
   the shipped `config.php` into the customer's copy).
5. Delete `src/backend/cache/dropdown_options.json` so dropdowns are
   re-fetched.
6. Smoke-test the widget. If `ConfigValidator` complains about a new required
   key, add it to the customer's config and retry.

A safer alternative is to keep the customer's `config.php` outside the widget
folder (e.g. in an environment-specific location) and `require` it from a
small `config.php` shim — that way release updates never touch customer
values.

---

## Customer customization checklist

- [ ] `THEME.primary` / `THEME.accent` set to brand colors
- [ ] `DATA_VIEW` points at the right table/view
- [ ] `FIELD_MAP` columns reflect customer schema (labels in their language)
- [ ] `STATUS_MAP` covers every status value the customer uses
- [ ] `ROW_ACTIONS` URL templates point at the right JobRouter processes
- [ ] `BASE_URL` + `TRACKING_PASSPHRASE` set per environment (dev / prod)
- [ ] `LOCALE` matches the customer's region

---

## Building from source

```powershell
cd widgets/react/simptrack
npm install
npm run build
```

Output lands in `dist/`. Copy `dist/` plus the `src/backend/` folder to the
JobRouter widget directory (or use the release zip workflow above).

# React + TypeScript + Vite + shadcn/ui

This is a template for a new Vite project with React, TypeScript, and shadcn/ui.
