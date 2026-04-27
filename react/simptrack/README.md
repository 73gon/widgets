# Simptrack Widget

Generische, kundenneutrale Starter-Variante des SimplifyTable-Widgets.
Wird für Kundendemos und als Basis für das Onboarding neuer Kunden eingesetzt.

Das Widget liefert den vollen Funktionsumfang (Filtern, Sortieren, Paginierung,
Benutzereinstellungen, Zeilenaktionen, Status-Badges, Autocomplete-Dropdowns,
…), enthält aber keine kundenspezifischen Spalten, kein Branding und keine
Geschäftslogik. Um Simptrack zu einem kundenspezifischen Widget zu machen,
kopieren, umbenennen und [src/backend/config.php](src/backend/config.php)
anpassen.

---

## Architektur in einem Absatz

`config.php` ist die einzige Wahrheitsquelle (Single Source of Truth).
`init.php` liefert das Schema (Spalten, Filter, Dropdown-Optionen, Theme,
Locale, Zeilenaktionen) an das Frontend. `query.php` baut das SQL aus
derselben Konfiguration. `ConfigValidator.php` validiert das Schema bei jedem
`init`-Aufruf (Regeln R1–R6) und liefert eine maschinenlesbare Fehlermeldung,
falls etwas nicht stimmt. Das React-Frontend ist ein dünner Konsument — es
hartkodiert weder Spaltennamen noch Filter-Keys oder Labels.

---

## Konfigurationsreferenz

Alle Blöcke befinden sich in [src/backend/config.php](src/backend/config.php).
Jeder Block ist unten in der Reihenfolge beschrieben, in der er in der Datei
auftaucht.

### 1. Datenbank

| Schlüssel           | Zweck                                                                           |
| ------------------- | ------------------------------------------------------------------------------- |
| `DB_TYPE`           | `'mssql'`, `'mysql'` oder `'auto'` (automatische Erkennung über JobRouter).     |
| `DATA_VIEW`         | Haupt-SQL-View bzw. -Tabelle, aus der das Widget liest.                         |
| `PREFERENCES_TABLE` | Tabelle für Benutzereinstellungen. Wird beim ersten Start automatisch erstellt. |
| `AUTH_COLUMN`       | Spalte mit der kommaseparierten Berechtigungsliste pro Zeile.                   |

### 2. URLs & Geheimnisse

| Schlüssel             | Zweck                                                             |
| --------------------- | ----------------------------------------------------------------- |
| `BASE_URL`            | Basis-URL des JobRouters beim Kunden (ohne abschließenden Slash). |
| `TRACKING_PASSPHRASE` | Geheimnis zur Erzeugung des MD5-Tracking-Keys für Vorgangs-Links. |

### 3. Theme

| Schlüssel     | Zweck                                                    |
| ------------- | -------------------------------------------------------- |
| `defaultMode` | `'light'` oder `'dark'` — Standardanzeige des Widgets.   |
| `primary`     | Primäre CSS-Farbe (Highlights, aktiver Scrollbalken, …). |
| `accent`      | Akzentfarbe für Badges und sekundäre Hervorhebungen.     |

### 4. `FIELD_MAP` (Spalten + Filter)

Array von Spaltendefinitionen. Jeder Eintrag kann einen `filter`-Block
enthalten; ist dieser gesetzt, dient die Spalte zusätzlich als Filter in der
Filterleiste.

Pro Eintrag:

- `id` — Frontend-Identifier.
- `label` — Spaltenüberschrift in der Tabelle.
- `type` — `'text'`, `'number'`, `'date'`, `'status'`, `'action'`.
- `align` — `'left'` | `'center'` | `'right'`.
- `dbColumn` — Spaltenname in der `DATA_VIEW`.
- `filter` — optional. Unterschlüssel:
  - `id`, `label`, `key` — Frontend-Identifier.
  - `type` — `'text'`, `'dropdown'`, `'autocomplete'`, `'daterange'`, `'numberrange'`.
  - `dbColumn`, `dbType` — SQL-Spalte und Vergleichstyp (`'text_like'`, `'in'`, `'between'`, …).
  - `defaultValue` — Initialwert.
  - `optionsKey` — bei dropdown/autocomplete: welcher Eintrag aus
    `DROPDOWN_SOURCES` / `STATIC_DROPDOWNS` geladen wird.
  - `listFilter` — optional `[Spalte, distinct]` zur Eingrenzung der Autocomplete.

### 5. `SPECIAL_FILTERS`

Eigenständige Filter, die nicht an eine einzelne Spalte gebunden sind
(Status-Gruppierungen, Laufzeit-Buckets usw.). Gleiche Struktur wie
`FIELD_MAP[].filter`.

### 6. `DROPDOWN_SOURCES`

Per Datenbank ermittelte Optionen. Ein Eintrag pro logischem Dropdown:

```php
'schritt' => [
  'table'    => 'DATA_VIEW',     // oder ein konkreter Tabellenname
  'valueCol' => 'step',
  'labelCol' => 'steplabel',
  'distinct' => true,
  'orderBy'  => 'step',          // optional
],
```

### 7. `STATIC_DROPDOWNS`

Hartkodierte Optionslisten, identisch geschlüsselt wie `DROPDOWN_SOURCES`.
Verwendet für Status, Priorität und alle anderen Enums, die nicht in der
Datenbank liegen.

### 8. `ROW_ACTIONS`

Schaltflächen in der Aktionsspalte pro Zeile. Jeder Eintrag definiert ein
Icon, ein Label, eine URL-Vorlage (mit `{spalte}`-Platzhaltern) und optionale
Berechtigungs-Hooks.

### 9. `STATUS_MAP`

Bildet rohe `status`-Werte aus der DB auf Anzeige-Label und Farb-Token ab.
Steuert die farbigen Status-Badges.

### 10. `CACHE`

| Schlüssel            | Zweck                                                  |
| -------------------- | ------------------------------------------------------ |
| `dropdownTtlSeconds` | Wie lange Dropdown-Optionen auf Platte gecacht werden. |
| `enabled`            | Hauptschalter für den Cache.                           |

### 11. `LOCALE`

Locale für Datums- und Zahlenformatierung (`'de-DE'`, `'en-US'`, …) plus
Zeitzone.

### 12. `COMPUTED_FIELDS` (optional)

Server-seitig berechnete Spalten (z. B. `laufzeit` aus zwei Zeitstempeln).

### 13. `DIAGNOSTICS` (optional)

Schaltet detailliertes Logging in `init.php` / `query.php` für
Support-Sitzungen ein.

### 14. `OPTION_MARKERS` (optional, in Simptrack nicht enthalten)

Markierungen für einzelne Optionen in Autocomplete-Dropdowns (z. B. SPVs,
Konzerngesellschaften). Im generischen Simptrack-Template nicht aktiv —
siehe das `simplifytable`-Widget für ein Beispiel.

---

## Erstinstallation bei einem neuen Kunden

1. **Build herunterladen.** Aktuelles Release-Zip aus den GitHub Releases
   laden (`Simptrack-vX.Y.Z.zip`). Es enthält das gebaute Frontend und das
   PHP-Backend.
2. **In JobRouter ablegen.** Inhalt nach
   `<jobrouter>/dashboard/MyWidgets/Simptrack/` entpacken. Den Ordner bei
   Bedarf umbenennen, wenn der Kunde einen eigenen Widget-Namen wünscht.
3. **`config.php` bearbeiten.** Mindestens anpassen:
   - `DB_TYPE`, `DATA_VIEW`, `AUTH_COLUMN`
   - `BASE_URL`, `TRACKING_PASSPHRASE`
   - `FIELD_MAP`-Spalten an die View des Kunden
   - `THEME`-Farben an das Branding des Kunden
4. **In JobRouter testen.** Dashboard öffnen. Schlägt die Validierung fehl,
   zeigt das Widget eine klare `ConfigValidationException` mit der genauen
   Regel und dem fehlerhaften Schlüssel.
5. **(Optional) `STATUS_MAP`, `ROW_ACTIONS`, `STATIC_DROPDOWNS`** an den
   Prozess des Kunden anpassen.

---

## Update auf ein neues Release

> **Wichtig: `config.php` niemals blind überschreiben.** Sie enthält
> kundenspezifische Werte (DATA_VIEW, BASE_URL, Geheimnisse, FIELD_MAP).

Empfohlener Ablauf:

1. Aktuelle `config.php` und ggf. den Inhalt von `cache/` sichern.
2. Neues Release-Zip aus den GitHub Releases laden.
3. Über den bestehenden Widget-Ordner entpacken.
4. **Gesicherte `config.php` zurückspielen** (oder neue optionale Schlüssel
   aus der ausgelieferten `config.php` in die Kunden-Variante übernehmen).
5. `cache/dropdown_options.json` löschen, damit Dropdowns neu geladen werden.
6. Widget testen. Meldet `ConfigValidator` einen neuen Pflichtschlüssel,
   diesen in die Kunden-Konfiguration ergänzen und erneut prüfen.

Sicherer ist es, die `config.php` des Kunden außerhalb des Widget-Ordners
abzulegen (z. B. an einer umgebungsspezifischen Stelle) und sie aus einer
kleinen `config.php`-Hülle per `require` einzubinden — so berührt ein
Release-Update nie die Kunden-Werte.

---

## Checkliste für die Kundenanpassung

- [ ] `THEME.primary` / `THEME.accent` auf die Markenfarben gesetzt
- [ ] `DATA_VIEW` zeigt auf die richtige Tabelle bzw. View
- [ ] `FIELD_MAP`-Spalten passen zum Schema (Labels in der Sprache des Kunden)
- [ ] `STATUS_MAP` deckt jeden vom Kunden genutzten Status-Wert ab
- [ ] `ROW_ACTIONS`-URLs zeigen auf die richtigen JobRouter-Prozesse
- [ ] `BASE_URL` + `TRACKING_PASSPHRASE` pro Umgebung gesetzt (Dev / Prod)
- [ ] `LOCALE` passt zur Region des Kunden

---

## Build aus dem Quellcode

```powershell
cd widgets/react/simptrack
bun install
bun run build
```

Das Ergebnis landet in `dist/`. Den Inhalt von `dist/` zusammen mit allen
Dateien aus `src/backend/` in den JobRouter-Widget-Ordner kopieren — oder
einfach den GitHub-Release-Workflow oben nutzen.

---

## Release-Pipeline

Releases werden über GitHub Actions gebaut
([.github/workflows/release-simptrack.yml](../../.github/workflows/release-simptrack.yml)).
Auslöser ist ein Tag im Format `simptrack-vX.Y.Z`. Beispiel für die erste
Version:

```powershell
git tag simptrack-v0.1.0
git push origin simptrack-v0.1.0
```

Der Workflow baut mit Bun, packt das Ergebnis in `Simptrack-vX.Y.Z.zip`
(Top-Level-Ordner `Simptrack/` mit dem `dist`-Inhalt und allen
Backend-Dateien, ohne `.md` und ohne `.github`) und veröffentlicht es als
GitHub Release.
