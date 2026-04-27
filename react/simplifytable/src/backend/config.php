<?php
/**
 * =============================================================================
 * SimplifyTable Widget — Customer Configuration
 * =============================================================================
 *
 * This is the SINGLE SOURCE OF TRUTH for all customer-specific values.
 * To deploy for a new customer, copy this file and adjust the values below.
 *
 * Sections:
 *   1. Database
 *   2. URLs & Secrets
 *   3. Theme
 *   4. Field Map (columns + filters)
 *   5. Special Filters (status, laufzeit, coor)
 *   6. Dropdown Sources (DB-queried options)
 *   7. Static Dropdowns (hardcoded options)
 *   8. Row Actions (action buttons per row)
 *   9. Status Map (DB status → display label)
 *   10. Cache
 */

$CONFIG = [];

// =============================================================================
// 1. DATABASE
// =============================================================================

/** Database type: 'mssql', 'mysql', or 'auto' (auto-detect) */
$CONFIG['DB_TYPE'] = 'auto';

/** Main SQL view / table used for data queries */
$CONFIG['DATA_VIEW'] = 'V_UEBERSICHTEN_WIDGET';

/** Table name for storing user preferences */
$CONFIG['PREFERENCES_TABLE'] = 'WIDGET_SIMPLIFYTABLE';

/** Column that holds the authorization / access list per row */
$CONFIG['AUTH_COLUMN'] = 'berechtigung';

// =============================================================================
// 2. URLS & SECRETS
// =============================================================================

/** Base URL of the customer's JobRouter instance (no trailing slash) */
$CONFIG['BASE_URL'] = 'https://jobrouter.empira-invest.com/jobrouter';

/** Passphrase used to generate MD5 tracking keys */
$CONFIG['TRACKING_PASSPHRASE'] = '6unYY_z[&%z-S,t2';

// =============================================================================
// 3. THEME
// =============================================================================

$CONFIG['THEME'] = [
  'primaryColor' => '#ffcc00',
  'defaultMode' => 'dark', // 'dark' or 'light'
];

// =============================================================================
// 4. FIELD MAP — Columns + Filters
// =============================================================================
//
// Each entry defines a table column AND optionally a filter.
//
// Column keys:
//   id        — frontend column ID (used in row data, column order, visibility)
//   label     — column header label
//   type      — render type: 'actions' | 'status' | 'date' | 'text' | 'currency'
//   align     — text alignment: 'left' | 'center' | 'right'
//   dbColumn  — database column name in DATA_VIEW (omit for computed columns)
//
// Filter keys (optional — omit 'filter' to have a column without a filter):
//   filter.type         — 'text' | 'dropdown' | 'autocomplete' | 'daterange' | 'numberrange'
//   filter.key          — state key used in URL params & filter state
//   filter.dbColumn     — DB column to filter on (defaults to column's dbColumn)
//   filter.dbType       — SQL filter type: 'text_like' | 'equality' | 'number_gte' | 'number_lte' | 'date_gte' | 'date_lte' | 'boolean_10'
//   filter.defaultValue — default value when filters are reset
//   filter.optionsKey   — key in dropdown options (for dropdown/autocomplete)
//   filter.rangeIds     — for daterange/numberrange: ['fromKey', 'toKey']
//   filter.listFilter   — for autocomplete multi-select: ['dbColumn', castToInt]
//

$CONFIG['FIELD_MAP'] = [
  [
    'id' => 'actions',
    'label' => '',
    'type' => 'actions',
    'align' => 'center',
  ],
  [
    'id' => 'status',
    'label' => 'Status',
    'type' => 'status',
    'align' => 'center',
    'dbColumn' => 'status',
    'filter' => ['type' => 'dropdown', 'key' => 'status', 'defaultValue' => 'all', 'optionsKey' => 'status'],
  ],
  [
    'id' => 'archivstatus',
    'label' => 'Archiv Status',
    'type' => 'text',
    'align' => 'center',
    'dbColumn' => 'archivstatus',
  ],
  [
    'id' => 'incident',
    'label' => 'Vorgangsnummer',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'incident',
    'filter' => ['type' => 'text', 'key' => 'incident', 'dbColumn' => 'incident', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'entryDate',
    'label' => 'Eingangsdatum',
    'type' => 'date',
    'align' => 'left',
    'dbColumn' => 'eingangsdatum',
  ],
  [
    'id' => 'stepLabel',
    'label' => 'Schritt',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'steplabel',
    'filter' => [
      'id' => 'schritt',
      'label' => 'Schritt',
      'type' => 'autocomplete',
      'key' => 'schritt',
      'defaultValue' => [],
      'optionsKey' => 'schritt',
      'listFilter' => ['step', true],
    ],
  ],
  [
    'id' => 'startDate',
    'label' => 'Startdatum (Schritt)',
    'type' => 'date',
    'align' => 'left',
    'dbColumn' => 'indate',
  ],
  [
    'id' => 'jobFunction',
    'label' => 'Rolle',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'jobfunction',
    'filter' => ['id' => 'rolle', 'label' => 'Rolle', 'type' => 'text', 'key' => 'rolle', 'dbColumn' => 'jobfunction', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'fullName',
    'label' => 'Bearbeiter',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'fullname',
    'filter' => ['id' => 'bearbeiter', 'label' => 'Bearbeiter', 'type' => 'text', 'key' => 'bearbeiter', 'dbColumn' => 'fullname', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'documentId',
    'label' => 'DokumentId',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'dokumentid',
    'filter' => ['id' => 'dokumentId', 'label' => 'DokumentId', 'type' => 'text', 'key' => 'dokumentId', 'dbColumn' => 'dokumentid', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'companyName',
    'label' => 'Gesellschaft',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'mandantname',
    'filter' => [
      'id' => 'gesellschaft',
      'label' => 'Gesellschaft',
      'type' => 'autocomplete',
      'key' => 'gesellschaft',
      'defaultValue' => [],
      'optionsKey' => 'gesellschaft',
      'listFilter' => ['mandantnr', false],
    ],
  ],
  [
    'id' => 'fund',
    'label' => 'Fonds',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'fond_abkuerzung',
    'filter' => [
      'id' => 'fonds',
      'label' => 'Fonds',
      'type' => 'autocomplete',
      'key' => 'fonds',
      'defaultValue' => [],
      'optionsKey' => 'fonds',
      'listFilter' => ['fond_abkuerzung', false],
    ],
  ],
  [
    'id' => 'creditorName',
    'label' => 'Kreditor',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'kredname',
    'filter' => ['id' => 'kreditor', 'label' => 'Kreditor', 'type' => 'text', 'key' => 'kreditor', 'dbColumn' => 'kredname', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'invoiceType',
    'label' => 'Rechnungstyp',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'rechnungstyp',
    'filter' => ['id' => 'rechnungstyp', 'label' => 'Rechnungstyp', 'type' => 'text', 'key' => 'rechnungstyp', 'dbColumn' => 'rechnungstyp', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'invoiceNumber',
    'label' => 'Rechnungsnummer',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'rechnungsnummer',
    'filter' => ['id' => 'rechnungsnummer', 'label' => 'Rechnungsnummer', 'type' => 'text', 'key' => 'rechnungsnummer', 'dbColumn' => 'rechnungsnummer', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'invoiceDate',
    'label' => 'Rechnungsdatum',
    'type' => 'date',
    'align' => 'left',
    'dbColumn' => 'rechnungsdatum',
    'filter' => [
      'id' => 'rechnungsdatum',
      'label' => 'Rechnungsdatum',
      'type' => 'daterange',
      'key' => 'rechnungsdatum',
      'defaultValue' => '',
      'rangeIds' => ['rechnungsdatumFrom', 'rechnungsdatumTo'],
      'rangeDbTypes' => ['date_gte', 'date_lte'],
      'dbColumn' => 'rechnungsdatum',
    ],
  ],
  [
    'id' => 'grossAmount',
    'label' => 'Bruttobetrag',
    'type' => 'currency',
    'align' => 'left',
    'dbColumn' => 'bruttobetrag',
    'filter' => [
      'id' => 'bruttobetrag',
      'label' => 'Bruttobetrag',
      'type' => 'numberrange',
      'key' => 'bruttobetrag',
      'defaultValue' => '',
      'rangeIds' => ['bruttobetragFrom', 'bruttobetragTo'],
      'rangeDbTypes' => ['number_gte', 'number_lte'],
      'dbColumn' => 'bruttobetrag',
    ],
  ],
  [
    'id' => 'dueDate',
    'label' => 'Fälligkeit',
    'type' => 'date',
    'align' => 'left',
    'dbColumn' => 'eskalation',
  ],
  [
    'id' => 'orderId',
    'label' => 'Auftragsnummer',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'coor_orderid',
  ],
  [
    'id' => 'paymentAmount',
    'label' => 'Zahlbetrag',
    'type' => 'currency',
    'align' => 'left',
    'dbColumn' => 'zahlbetrag',
  ],
  [
    'id' => 'paymentDate',
    'label' => 'Zahldatum',
    'type' => 'date',
    'align' => 'left',
    'dbColumn' => 'zahldatum',
  ],
  [
    'id' => 'chargeable',
    'label' => 'Weiterbelasten',
    'type' => 'text',
    'align' => 'center',
    'dbColumn' => 'berechenbar',
    'filter' => [
      'id' => 'weiterbelasten',
      'label' => 'Weiterbelasten',
      'type' => 'dropdown',
      'key' => 'weiterbelasten',
      'dbColumn' => 'berechenbar',
      'dbType' => 'equality',
      'defaultValue' => 'all',
      'optionsKey' => 'weiterbelasten',
    ],
  ],
  [
    'id' => 'kostenuebernahme',
    'label' => 'Kostenübernahme',
    'type' => 'text',
    'align' => 'center',
    'dbColumn' => 'kostenuebernahme',
    'filter' => [
      'type' => 'dropdown',
      'key' => 'kostenuebernahme',
      'dbColumn' => 'kostenuebernahme',
      'dbType' => 'boolean_10',
      'defaultValue' => 'all',
      'optionsKey' => 'kostenuebernahme',
    ],
  ],
];

// =============================================================================
// 5. SPECIAL FILTERS
// =============================================================================
// These filters have complex SQL logic and are not tied to a single column.
// Each defines a standalone filter entry for the frontend + a handler name
// used in query.php to generate WHERE clauses.

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
  [
    'id' => 'coor',
    'label' => 'Coor',
    'type' => 'dropdown',
    'key' => 'coor',
    'defaultValue' => 'all',
    'optionsKey' => 'coor',
    'handler' => 'coor',
  ],
];

// Column used for laufzeit DATEDIFF calculation
$CONFIG['LAUFZEIT_COLUMN'] = 'indate';

// Laufzeit range definitions: label => [minDays, maxDays] (null = unbounded)
$CONFIG['LAUFZEIT_RANGES'] = [
  '0-5 Tage' => [0, 5],
  '6-10 Tage' => [6, 10],
  '11-20 Tage' => [11, 20],
  '21+ Tage' => [21, null],
];

// Column used for coor flag
$CONFIG['COOR_COLUMN'] = 'coorflag';

// Column used for status
$CONFIG['STATUS_COLUMN'] = 'status';

// Column used for escalation date (due date in status filter)
$CONFIG['ESCALATION_COLUMN'] = 'eskalation';

// =============================================================================
// 6. DROPDOWN SOURCES (DB-queried)
// =============================================================================
// Each entry defines a dropdown whose options are queried from the database.
// 'table' — table/view to query ('DATA_VIEW' means use $CONFIG['DATA_VIEW'])
// 'valueCol' — column for option value/id
// 'labelCol' — column for option label

$CONFIG['DROPDOWN_SOURCES'] = [
  'schritt' => [
    'table' => 'DATA_VIEW',
    'valueCol' => 'step',
    'labelCol' => 'steplabel',
    'distinct' => true,
  ],
  'gesellschaft' => [
    'table' => 'JD_GESELLSCHAFTEN',
    'valueCol' => 'NUMMER',
    'labelCol' => 'NAME',
    'orderBy' => 'NUMMER',
  ],
  'fonds' => [
    'table' => 'JD_FONDS',
    'valueCol' => 'ABKUERZUNG',
    'labelCol' => 'ABKUERZUNG',
  ],
];

// =============================================================================
// 6b. OPTION MARKERS (per-option tags on autocomplete dropdowns)
// =============================================================================
// Flags subsets of options inside a DROPDOWN_SOURCES entry so the frontend can
// render a colored dot per marked option and a one-click select-all badge.
// Multiple markers may target the same optionsKey (e.g. SPVs + Konzern).
//
// Each entry:
//   id         — unique identifier (also used as marker tag on options)
//   optionsKey — DROPDOWN_SOURCES key whose options should be marked
//   column     — additional column on that source table read for marking
//   value      — string OR array of strings; option is marked when column matches
//   label      — text shown on the toggle badge in the UI
//   color      — CSS color used for the badge background and the dot
//
// Remove the array (or leave it empty) to disable markers entirely.

$CONFIG['OPTION_MARKERS'] = [
  [
    'id' => 'spv',
    'optionsKey' => 'gesellschaft',
    'column' => 'ROLLE',
    'value' => '0000',
    'label' => 'SPVs',
    'color' => '#14b8a6',
  ],
  [
    'id' => 'konzern',
    'optionsKey' => 'gesellschaft',
    'column' => 'ROLLE',
    'value' => ['0001', '0002', '0003', '0004', '0007', '0011', '0015'],
    'label' => 'Konzerngesellschaften',
    'color' => '#a855f7',
  ],
];

// =============================================================================
// 7. STATIC DROPDOWNS (hardcoded)
// =============================================================================

$CONFIG['STATIC_DROPDOWNS'] = [
  'status' => [
    ['id' => 'completed', 'label' => 'Beendet'],
    ['id' => 'aktiv_alle', 'label' => 'Aktiv Alle'],
    ['id' => 'faellig', 'label' => 'Aktiv Fällig'],
    ['id' => 'not_faellig', 'label' => 'Aktiv Nicht Fällig'],
  ],
  'laufzeit' => [
    ['id' => '0-5 Tage', 'label' => '0-5 Tage'],
    ['id' => '6-10 Tage', 'label' => '6-10 Tage'],
    ['id' => '11-20 Tage', 'label' => '11-20 Tage'],
    ['id' => '21+ Tage', 'label' => '21+ Tage'],
  ],
  'coor' => [
    ['id' => 'Ja', 'label' => 'Ja'],
    ['id' => 'Nein', 'label' => 'Nein'],
  ],
  'weiterbelasten' => [
    ['id' => 'Ja', 'label' => 'Ja'],
    ['id' => 'Nein', 'label' => 'Nein'],
  ],
  'kostenuebernahme' => [
    ['id' => 'Ja', 'label' => 'Ja'],
    ['id' => 'Nein', 'label' => 'Nein'],
  ],
];

// =============================================================================
// 8. ROW ACTIONS
// =============================================================================
// Action buttons displayed per row in the actions column.
// URL templates support placeholders: {processid}, {username}, {key}, {documentId}, {incident}, {BASE_URL}
// 'condition' — optional: only show if row matches (e.g., 'status=completed')
// 'icon' — icon identifier for the frontend: 'history', 'invoice', 'protocol'

$CONFIG['ROW_ACTIONS'] = [
  [
    'id' => 'history',
    'label' => 'Vorgangshistorie anzeigen',
    'icon' => 'history',
    'enabled' => true,
    'urlTemplate' => '{BASE_URL}/index.php?cmd=Tracking_ShowTracking&jrprocessid={processid}&display=popup&jrkey={key}',
    'target' => 'popup',
    'popupSize' => [1000, 700],
  ],
  [
    'id' => 'invoice',
    'label' => 'Rechnung öffnen',
    'icon' => 'invoice',
    'enabled' => true,
    'urlTemplate' => '{BASE_URL}/FIBU_URL.php?dokument={documentId}',
    'target' => '_blank',
  ],
  [
    'id' => 'protocol',
    'label' => 'Protokoll öffnen',
    'icon' => 'protocol',
    'enabled' => true,
    'urlTemplate' => '{BASE_URL}/PROTOCOL_URL.php?dokument={documentId}',
    'target' => '_blank',
    'condition' => 'status=completed',
  ],
];

// =============================================================================
// 9. STATUS MAP
// =============================================================================
// Maps raw DB status values to frontend display labels and status types.
// 'type' is used for row coloring: 'completed', 'due', 'not_due', 'unknown'

$CONFIG['STATUS_MAP'] = [
  'completed' => ['label' => 'Beendet', 'type' => 'completed'],
  'rest' => [
    // For 'rest' status, the actual label depends on the escalation date.
    // This is handled in mapRow() — 'Faellig' or 'Nicht Faellig'.
    'label' => null, // computed at runtime
    'type' => null,
  ],
];

$CONFIG['STATUS_LABELS'] = [
  'completed' => 'Beendet',
  'due' => 'Faellig',
  'not_due' => 'Nicht Faellig',
];

// =============================================================================
// 10. CACHE
// =============================================================================

/** Dropdown cache TTL in seconds */
$CONFIG['CACHE_TTL'] = 600;

// =============================================================================
// 11. LOCALE (for frontend)
// =============================================================================

$CONFIG['LOCALE'] = [
  'language' => 'de-DE',
  'currency' => 'EUR',
];

// =============================================================================
// 12. COMPUTED FIELDS
// =============================================================================
// Additional fields derived from row data (not direct DB column mappings).
// Boolean columns (filter.dbType === 'boolean_10') are auto-mapped to Ja/Nein
// in mapRow() — no COMPUTED_FIELDS entry required for them.
// Use this map for NON-boolean computed fields (custom mappings only).

$CONFIG['COMPUTED_FIELDS'] = [];

// =============================================================================
// 13. DIAGNOSTICS
// =============================================================================

/** Directory where daily log files are written (created automatically). */
$CONFIG['LOG_DIR'] = __DIR__ . '/logs';

/** When true, every final SQL query is written to the log at DEBUG level. */
$CONFIG['DEBUG_LOG'] = false;

return $CONFIG;
