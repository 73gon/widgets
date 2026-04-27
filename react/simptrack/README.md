# Simptrack Widget

Generische, kundenneutrale Starter-Variante des SimplifyTable-Widgets.
Dient für Kundendemos und als Basis für das Onboarding neuer Kunden.

Volles Feature-Set: Filtern, Sortieren, Paginierung, Benutzereinstellungen,
Zeilenaktionen, Status-Badges, Autocomplete-Dropdowns, Theme/Locale. Aber:
keine kundenspezifischen Spalten, kein Branding, keine Geschäftslogik.

Onboarding eines neuen Kunden = `src/backend/config.php` anpassen
(typisch ~100 Zeilen).

---

## Architektur in einem Absatz

`config.php` ist die einzige Konfigurationsquelle. Beim Laden geht sie durch
**`ConfigNormalizer`** (füllt alle optionalen Schlüssel mit Defaults und
expandiert die kompakte Filter-Syntax) und danach durch
**`ConfigValidator`** (prüft Spalten, Dropdown-Quellen, Row-Action-Tokens
usw., siehe Regeln R1–R7). `init.php` schickt das Schema ans Frontend,
`query.php` baut das SQL — beide aus derselben Config. Das React-Frontend
hartkodiert keine Spalten, Filter oder Labels.

---

## Erstinstallation bei einem neuen Kunden

1. **Build herunterladen.** Aktuelles Release-Zip aus den GitHub Releases
   laden (`Simptrack-vX.Y.Z.zip`). Es enthält das gebaute Frontend und das
   PHP-Backend — kein Bun, kein Node, kein Build-Step beim Kunden.
2. **In JobRouter ablegen.** Inhalt nach
   `<jobrouter>/dashboard/MyWidgets/Simptrack/` entpacken. Den Ordner bei
   Bedarf umbenennen, falls der Kunde einen anderen Widget-Namen wünscht.
3. **`src/backend/config.php` anpassen.** Mindestens:
   - `DATA_VIEW` — die Haupt-View / -Tabelle
   - `BASE_URL` und `TRACKING_PASSPHRASE`
   - `FIELD_MAP` — Spalten + Filter (kompakte Syntax, siehe unten)
4. **Optional:** `THEME` (Farben), `ROW_AUTH` (Berechtigungen),
   `STATUS_COLUMN` / `ESCALATION_COLUMN` (Status-Badge), `ROW_ACTIONS`
   (Buttons in der Aktionsspalte). Alles andere hat sinnvolle Defaults.
5. **In JobRouter testen.** Dashboard öffnen. Schlägt die Validierung fehl,
   zeigt das Widget eine klare `ConfigValidator`-Fehlermeldung mit Regel
   und betroffenem Schlüssel.

> Detaillierte Referenz aller Filter-Shorthands, Defaults und Override-Keys:
> [FIELD_REGISTRY_GUIDE.md](FIELD_REGISTRY_GUIDE.md).

---

## `config.php` in zwei Minuten

Die kompakte Form sieht so aus:

```php
<?php
$CONFIG = [];

// 1. Datenbank
$CONFIG['DATA_VIEW'] = 'ER_VORGANG';

// 2. URLs & Geheimnisse
$CONFIG['BASE_URL']            = 'https://jobrouter.kunde.de';
$CONFIG['TRACKING_PASSPHRASE'] = 'change-me';

// 3. Spalten + Filter (kompakte Form)
$CONFIG['FIELD_MAP'] = [
  ['id' => 'actions', 'type' => 'actions'],
  ['id' => 'incident',    'label' => 'Vorgangsnummer', 'dbColumn' => 'incident',       'filter' => 'text'],
  ['id' => 'entryDate',   'label' => 'Eingangsdatum',  'dbColumn' => 'eingangsdatum',  'type'   => 'date'],
  ['id' => 'invoiceDate', 'label' => 'Rechnungsdatum', 'dbColumn' => 'rechnungsdatum', 'type'   => 'date',     'filter' => 'daterange'],
  ['id' => 'grossAmount', 'label' => 'Bruttobetrag',   'dbColumn' => 'bruttobetrag',   'type'   => 'currency', 'filter' => 'numberrange'],
  ['id' => 'stepLabel',   'label' => 'Schritt',        'dbColumn' => 'steplabel',      'filter' => 'autocomplete'],
];

// 4. Optionale DB-Dropdowns (Schlüssel = Field-id)
$CONFIG['DROPDOWN_SOURCES'] = [
  'stepLabel' => ['table' => 'DATA_VIEW', 'valueCol' => 'steplabel', 'distinct' => true],
];

// 5. Optionale Zeilenaktionen
$CONFIG['ROW_ACTIONS'] = [
  ['id' => 'open', 'label' => 'Öffnen', 'icon' => 'eye',
   'urlTemplate' => '{BASE_URL}/jobrouter/index.php?navigation=incident_show&processid={processid}&key={key}'],
];
```

Das ist alles. Theme, Locale, Cache-TTL, Status-Labels, Berechtigungen,
Preferences-Tabelle usw. werden automatisch mit Defaults belegt.

> Vollständiges Beispiel mit allen typischen Spalten:
> [src/backend/config.php](src/backend/config.php).
> Reales Kunden-Beispiel:
> [src/backend/customers/bremer/config.php](src/backend/customers/bremer/config.php).

### Filter-Shorthands

| Shorthand        | Wirkung                                                                            |
| ---------------- | ---------------------------------------------------------------------------------- |
| `'text'`         | Free-Text, `LOWER(col) LIKE '%val%'`                                               |
| `'date'`         | Einzel-Datum, `col >= val`                                                         |
| `'daterange'`    | Von / Bis Datum                                                                    |
| `'numberrange'`  | Von / Bis Zahl                                                                     |
| `'dropdown'`     | Single-Select-Dropdown (Optionsliste über `STATIC_DROPDOWNS` / `DROPDOWN_SOURCES`) |
| `'autocomplete'` | Multi-Select-Chips, `IN (…)` (Optionsliste über `DROPDOWN_SOURCES`)                |
| `'boolean'`      | Ja / Nein, automatisch zu `1` / `0` gemappt                                        |

Wenn die Shorthand nicht reicht, kann `'filter'` weiterhin ein vollständiges
Array sein — siehe **Verbose `FIELD_MAP` form (escape hatch)** in
[FIELD_REGISTRY_GUIDE.md](FIELD_REGISTRY_GUIDE.md#verbose-field_map-form-escape-hatch).

### Erweiterte Optionen

Der `ConfigNormalizer` kennt für jeden optionalen Schlüssel einen Default
(`THEME`, `LOCALE`, `ROW_AUTH`, `CACHE_TTL`, `STATUS_LABELS`,
`PREFERENCES_TABLE` …). Liste, Defaults und Override-Beispiele:
[FIELD_REGISTRY_GUIDE.md → Advanced / overrides](FIELD_REGISTRY_GUIDE.md#advanced--overrides--top-level-config-keys).

---

## Update auf ein neues Release

> **Wichtig: `config.php` niemals blind überschreiben.** Sie enthält
> kundenspezifische Werte (`DATA_VIEW`, `BASE_URL`, Geheimnisse, `FIELD_MAP`).

1. Aktuelle `config.php` und ggf. `cache/`-Inhalt sichern.
2. Neues Release-Zip laden, über den bestehenden Widget-Ordner entpacken.
3. **Gesicherte `config.php` zurückspielen.** Da neue optionale Schlüssel
   automatisch mit Defaults belegt werden, sind Migrations-Schritte selten
   nötig.
4. `cache/dropdown_options.json` löschen, damit Dropdowns neu geladen werden.
5. Widget testen. Meldet `ConfigValidator` einen neuen Pflichtschlüssel,
   diesen ergänzen und erneut prüfen.

Sicherer ist es, die `config.php` des Kunden außerhalb des Widget-Ordners
abzulegen und aus einer kleinen `config.php`-Hülle per `require` einzubinden
— so berührt ein Release-Update nie die Kunden-Werte.

---

## Build aus dem Quellcode

Nur nötig, wenn man am Frontend entwickelt. Für reine Konfigurations-Anpassungen
beim Kunden reicht das Release-Zip.

```powershell
cd widgets/react/simptrack
bun install
bun run build
```

Das Ergebnis landet in `dist/`. Den Inhalt von `dist/` zusammen mit allen
Dateien aus `src/backend/` in den JobRouter-Widget-Ordner kopieren — oder
einfach den Release-Workflow benutzen.

---

## Release-Pipeline

Releases werden über GitHub Actions gebaut
([.github/workflows/release-simptrack.yml](../../.github/workflows/release-simptrack.yml)).
Auslöser ist ein Tag im Format `vX.Y.Z`:

```powershell
git tag v0.1.0
git push origin v0.1.0
```

Der Workflow baut mit Bun, packt das Ergebnis in `Simptrack-vX.Y.Z.zip`
(Top-Level-Ordner `Simptrack/` mit dem `dist`-Inhalt und allen
Backend-Dateien, ohne `.md` und ohne `.github`) und veröffentlicht es als
GitHub Release. Das Zip ist direkt in JobRouter installierbar.

---

## Checkliste für die Kundenanpassung

- [ ] `DATA_VIEW` zeigt auf die richtige Tabelle / View
- [ ] `BASE_URL` + `TRACKING_PASSPHRASE` pro Umgebung gesetzt (Dev / Prod)
- [ ] `FIELD_MAP` deckt alle anzuzeigenden Spalten ab
- [ ] `DROPDOWN_SOURCES` für jede Autocomplete-/Dropdown-Spalte gesetzt
- [ ] `ROW_ACTIONS`-URLs zeigen auf die richtigen JobRouter-Prozesse
- [ ] Optional: `THEME` an die Markenfarben angepasst
- [ ] Optional: `ROW_AUTH` gesetzt, falls nicht jede Zeile für jeden sichtbar sein soll
- [ ] Optional: `STATUS_COLUMN` + `ESCALATION_COLUMN` gesetzt, falls Status-Badge gewünscht
