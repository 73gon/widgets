<?php
/**
 * =============================================================================
 * Simptrack Widget — Customer Configuration: NAME
 * =============================================================================
 *
 * Single source of truth for all customer-specific values.
 *
 * Compact field syntax
 * --------------------
 *   ['id' => 'mandant', 'label' => 'Mandant', 'dbColumn' => 'Mandant', 'filter' => 'text']
 *
 * Filter shorthands:
 *   'text' | 'date' | 'daterange' | 'numberrange' | 'dropdown' | 'autocomplete' | 'boolean'
 *
 * Auto-derived field defaults (override only when needed):
 *   id → camelCase(dbColumn) · label → id · type → 'text' · align → 'left'
 *
 * Every section below marked OPTIONAL has a built-in default in
 * ConfigNormalizer.php. Uncomment only what you need to override.
 *
 */

$CONFIG = [];

// =============================================================================
// 1. DATABASE                                                          REQUIRED
// =============================================================================

$CONFIG['DATA_VIEW'] = 'V_SIMPTRACK_DEMO';

// OPTIONAL — DB driver. Default 'auto' (detected via JobRouter).
// $CONFIG['DB_TYPE'] = 'mssql';     // 'mssql' | 'mysql' | 'auto'

// OPTIONAL — per-user preferences table (auto-created on first run).
// $CONFIG['PREFERENCES_TABLE'] = 'WIDGET_SIMPTRACK';

// OPTIONAL — dropdown-options cache lifetime in seconds. Default 600.
// $CONFIG['CACHE_TTL'] = 600;

// OPTIONAL — maximum rows allowed for a single Excel export. Default null (no limit).
// Set this to prevent server overload when the data view contains many rows.
// When the active filter would return more than this number of rows, the export
// is blocked and the user sees an error message asking them to apply more filters.
// $CONFIG['EXPORT_MAX_ROWS'] = 5000;

// =============================================================================
// 2. URLS & SECRETS                                                    REQUIRED
// =============================================================================

/** Base URL of the customer's JobRouter instance (no trailing slash). */
$CONFIG['BASE_URL'] = 'https://jobrouter.example.com/jobrouter';

/**
 * Passphrase used to generate MD5 tracking keys ({key} placeholder in row
 * actions). MUST match the customer's JobRouter tracking passphrase, otherwise
 * Tracking_ShowTracking returns ZUGRIFF VERWEIGERT.
 */
$CONFIG['TRACKING_PASSPHRASE'] = 'change-me-per-customer';

// =============================================================================
// 3. ROW-LEVEL ACCESS (ROW_AUTH)                                       OPTIONAL
// =============================================================================
//
// Default: ['mode' => 'none'] — every user sees every row.
// Available modes:
//   $CONFIG['ROW_AUTH'] = ['mode' => 'equals',        'column' => 'owner'];
//   $CONFIG['ROW_AUTH'] = ['mode' => 'list_contains', 'column' => 'berechtigung'];
//   $CONFIG['ROW_AUTH'] = ['mode' => 'custom',
//     'sql' => "EXISTS (SELECT 1 FROM ACL a WHERE a.id = t.id AND a.user = '{user}')"];

// =============================================================================
// 4. THEME                                                             OPTIONAL
// =============================================================================
//
// Default: ['primaryColor' => '#ffcc00', 'defaultMode' => 'dark'].
// $CONFIG['THEME'] = [
//   'primaryColor' => '#0066cc',     // brand colour
//   'defaultMode'  => 'light',       // 'light' | 'dark'
// ];

// =============================================================================
// 5. STATUS BADGE (optional feature)                                   OPTIONAL
// =============================================================================
//
// Set both columns to enable the coloured status badge column. Without these
// the status feature stays disabled and no `status` field is emitted.
//
// $CONFIG['STATUS_COLUMN']     = 'status';      // values: 'completed' / 'rest'
// $CONFIG['ESCALATION_COLUMN'] = 'eskalation';  // due-date column for active rows
//
// Default labels: ['completed' => 'Beendet', 'due' => 'Fällig', 'not_due' => 'Nicht fällig']
// $CONFIG['STATUS_LABELS'] = [
//   'completed' => 'Done',
//   'due'       => 'Overdue',
//   'not_due'   => 'On track',
// ];

// =============================================================================
// 6. FIELD MAP — Columns + Filters                                     REQUIRED
// =============================================================================

$CONFIG['FIELD_MAP'] = [
  ['id' => 'actions', 'type' => 'actions'],
  ['id' => 'incident', 'label' => 'Vorgangsnummer', 'dbColumn' => 'incident', 'filter' => 'text'],
  ['id' => 'mandant', 'label' => 'Mandant', 'dbColumn' => 'mandant', 'filter' => 'text'],
  ['id' => 'entryDate', 'label' => 'Eingangsdatum', 'dbColumn' => 'eingangsdatum', 'type' => 'date'],
  ['id' => 'stepLabel', 'label' => 'Schritt', 'dbColumn' => 'steplabel', 'filter' => 'autocomplete'],
  ['id' => 'startDate', 'label' => 'Startdatum (Schritt)', 'dbColumn' => 'indate', 'type' => 'date'],
  ['id' => 'fullName', 'label' => 'Bearbeiter', 'dbColumn' => 'fullname', 'filter' => 'text'],
  ['id' => 'creditorName', 'label' => 'Kreditor', 'dbColumn' => 'kredname', 'filter' => 'text'],
  ['id' => 'invoiceNumber', 'label' => 'Rechnungsnummer', 'dbColumn' => 'rechnungsnummer', 'filter' => 'text'],
  ['id' => 'invoiceDate', 'label' => 'Rechnungsdatum', 'dbColumn' => 'rechnungsdatum', 'type' => 'date', 'filter' => 'daterange'],
  ['id' => 'netAmount', 'label' => 'Nettobetrag', 'dbColumn' => 'nettobetrag', 'type' => 'currency', 'filter' => 'numberrange'],
  ['id' => 'grossAmount', 'label' => 'Bruttobetrag', 'dbColumn' => 'bruttobetrag', 'type' => 'currency', 'filter' => 'numberrange'],
  ['id' => 'dueDate', 'label' => 'Fälligkeit', 'dbColumn' => 'eskalation', 'type' => 'date'],
  ['id' => 'paymentAmount', 'label' => 'Zahlbetrag', 'dbColumn' => 'zahlbetrag', 'type' => 'currency'],
  ['id' => 'paymentDate', 'label' => 'Zahldatum', 'dbColumn' => 'zahldatum', 'type' => 'date'],
  ['id' => 'documentId', 'label' => 'Dokument-ID', 'dbColumn' => 'documentid', 'filter' => 'text'],
];

// =============================================================================
// 7. ROW ACTIONS                                                       OPTIONAL
// =============================================================================
//
// Allowed placeholders in urlTemplate: {BASE_URL}, any FIELD_MAP id, plus
// per-row tokens {processid}, {key} (MD5 from TRACKING_PASSPHRASE),
// {username}, {documentId}, {incident}.

$CONFIG['ROW_ACTIONS'] = [
  [
    'id' => 'history',
    'label' => 'Vorgangshistorie anzeigen',
    'icon' => 'history',
    'enabled' => true,
    // {key} is the MD5 hash built from processid + TRACKING_PASSPHRASE + username.
    'urlTemplate' => '{BASE_URL}/index.php?cmd=Tracking_ShowTracking&jrprocessid={processid}&display=popup&jrkey={key}',
    'target' => 'popup',
    'popupSize' => [1000, 700],
  ],
  [
    'id' => 'invoice',
    'label' => 'Beleg anzeigen',
    'icon' => 'invoice',
    'enabled' => true,
    'urlTemplate' => '{BASE_URL}/FIBU_URL.php?dokument={documentNumber}',
    'target' => 'popup',
    'popupSize' => [1000, 800],
  ],
];

// =============================================================================
// 8. DROPDOWN SOURCES — DB-queried option lists                        OPTIONAL
// =============================================================================
//
// Keys MUST match the field id of the consuming filter.

$CONFIG['DROPDOWN_SOURCES'] = [
  'stepLabel' => [
    'table' => 'DATA_VIEW',
    'valueCol' => 'step',
    'labelCol' => 'steplabel',
    'distinct' => true,
  ],
];

// =============================================================================
// 9. STATIC DROPDOWNS — hard-coded option lists                        OPTIONAL
// =============================================================================
//
// `status` and `laufzeit` get auto-injected by the normalizer when their
// fields exist. Use this only for additional enums.
//
// $CONFIG['STATIC_DROPDOWNS'] = [
//   'priority' => [
//     ['value' => 'low',    'label' => 'Niedrig'],
//     ['value' => 'normal', 'label' => 'Normal'],
//     ['value' => 'high',   'label' => 'Hoch'],
//   ],
// ];

// =============================================================================
// 10. LAUFZEIT (age-bucket special filter)                             OPTIONAL
// =============================================================================
//
// Buckets active rows by age in days. STATIC_DROPDOWNS['laufzeit'] is
// auto-derived from the range labels.

$CONFIG['LAUFZEIT_COLUMN'] = 'indate';

$CONFIG['LAUFZEIT_RANGES'] = [
  '0-5 Tage' => [0, 5],
  '6-10 Tage' => [6, 10],
  '11-20 Tage' => [11, 20],
  '21+ Tage' => [21, null],
];

$CONFIG['SPECIAL_FILTERS'] = [
  [
    'id' => 'laufzeit',
    'label' => 'Laufzeit',
    'type' => 'dropdown',
    'key' => 'laufzeit',
    'defaultValue' => 'all',
    'optionsKey' => 'laufzeit',
    'handler' => 'laufzeit',
  ],
];

// =============================================================================
// 11. COMPUTED FIELDS                                                  OPTIONAL
// =============================================================================
//
// Server-side value mappings. boolean_10 columns are mapped to Ja/Nein
// automatically — use this only for non-boolean enums.
//
// $CONFIG['COMPUTED_FIELDS'] = [
//   'ampel' => [
//     'source'  => 'ampel',
//     'mapping' => [1 => 'Rot', 2 => 'Gelb', 3 => 'Grün', null => ''],
//   ],
// ];

// =============================================================================
// 12. DIAGNOSTICS                                                      OPTIONAL
// =============================================================================
//
// Set DEBUG_LOG = true to enable extensive performance and query logging.
// All log files are written to: <widget>/src/backend/logs/simptrack-YYYY-MM-DD.log
// (JSON-line format, daily rotation, 14-day retention)
//
// What gets logged when DEBUG_LOG = true:
//   init.request          — request start (user, data view)
//   init.dropdown.cache   — cache HIT (age_s, ttl_s) or MISS (source count)
//   init.dropdown.query   — each dropdown SQL + execution time in ms
//   init.preferences.query — user preferences SELECT + ms
//   init.result           — total init time, column count, dropdown keys
//
//   query.request         — active filters, pagination params, sort
//   query.sql             — every SQL query + execution time in ms
//   query.result          — total rows in DB, rows returned, total ms
//
//   savepreferences.check  — existence-check SELECT + ms
//   savepreferences.update — UPDATE execution time in ms
//   savepreferences.insert — INSERT execution time in ms
//   savepreferences.result — total time, action taken (insert/update)
//
//   ERROR entries are always written regardless of DEBUG_LOG.
//
// Disable on production once debugging is complete (default: false).

// $CONFIG['DEBUG_LOG'] = true;
// $CONFIG['LOG_DIR']   = __DIR__ . '/../../logs';   // custom log directory

return $CONFIG;
