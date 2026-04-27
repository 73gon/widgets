<?php
/**
 * =============================================================================
 * Simptrack Widget — Generic Customer Configuration (Demo / Starter)
 * =============================================================================
 *
 * This is the SINGLE SOURCE OF TRUTH for all customer-specific values.
 * It ships as a neutral starter that demonstrates the full feature set of the
 * widget without any customer-specific business logic. To onboard a customer:
 *
 *   1. Point DATA_VIEW at the customer's invoice/process view.
 *   2. Adjust FIELD_MAP entries to match the columns of that view.
 *   3. Set BASE_URL + TRACKING_PASSPHRASE for their JobRouter instance.
 *   4. Optionally re-theme via $CONFIG['THEME'].
 *
 * Sections:
 *   1. Database
 *   2. URLs & Secrets
 *   3. Theme
 *   4. Field Map (columns + filters)
 *   5. Special Filters (status, laufzeit)
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

/**
 * Main SQL view / table used for data queries.
 * Replace with the customer's process / invoice view.
 */
$CONFIG['DATA_VIEW'] = 'V_SIMPTRACK_DEMO';

/** Table name for storing user preferences (auto-created on first run). */
$CONFIG['PREFERENCES_TABLE'] = 'WIDGET_SIMPTRACK';

/** Column that holds the authorization / access list per row. */
$CONFIG['AUTH_COLUMN'] = 'berechtigung';

// =============================================================================
// 2. URLS & SECRETS
// =============================================================================

/** Base URL of the customer's JobRouter instance (no trailing slash). */
$CONFIG['BASE_URL'] = 'https://jobrouter.example.com/jobrouter';

/** Passphrase used to generate MD5 tracking keys. Replace per customer. */
$CONFIG['TRACKING_PASSPHRASE'] = 'change-me-per-customer';

// =============================================================================
// 3. THEME
// =============================================================================

$CONFIG['THEME'] = [
  'primaryColor' => '#3b82f6',
  'defaultMode' => 'dark', // 'dark' or 'light'
];

// =============================================================================
// 4. FIELD MAP — Columns + Filters
// =============================================================================
//
// Neutral starter set. Add / remove entries to match the customer's data view.
// See FIELD_REGISTRY_GUIDE.md for the full schema reference.
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
    'id' => 'fullName',
    'label' => 'Bearbeiter',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'fullname',
    'filter' => ['id' => 'bearbeiter', 'label' => 'Bearbeiter', 'type' => 'text', 'key' => 'bearbeiter', 'dbColumn' => 'fullname', 'dbType' => 'text_like', 'defaultValue' => ''],
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
];

// =============================================================================
// 5. SPECIAL FILTERS
// =============================================================================

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

$CONFIG['LAUFZEIT_COLUMN'] = 'indate';

$CONFIG['LAUFZEIT_RANGES'] = [
  '0-5 Tage' => [0, 5],
  '6-10 Tage' => [6, 10],
  '11-20 Tage' => [11, 20],
  '21+ Tage' => [21, null],
];

$CONFIG['COOR_COLUMN'] = 'coorflag';
$CONFIG['STATUS_COLUMN'] = 'status';
$CONFIG['ESCALATION_COLUMN'] = 'eskalation';

// =============================================================================
// 6. DROPDOWN SOURCES (DB-queried)
// =============================================================================

$CONFIG['DROPDOWN_SOURCES'] = [
  'schritt' => [
    'table' => 'DATA_VIEW',
    'valueCol' => 'step',
    'labelCol' => 'steplabel',
    'distinct' => true,
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
];

// =============================================================================
// 8. ROW ACTIONS
// =============================================================================

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
];

// =============================================================================
// 9. STATUS MAP
// =============================================================================

$CONFIG['STATUS_MAP'] = [
  'completed' => ['label' => 'Beendet', 'type' => 'completed'],
  'rest' => [
    'label' => null,
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

$CONFIG['CACHE_TTL'] = 600;

// =============================================================================
// 11. LOCALE
// =============================================================================

$CONFIG['LOCALE'] = [
  'language' => 'de-DE',
  'currency' => 'EUR',
];

// =============================================================================
// 12. COMPUTED FIELDS
// =============================================================================

$CONFIG['COMPUTED_FIELDS'] = [];

// =============================================================================
// 13. DIAGNOSTICS
// =============================================================================

$CONFIG['LOG_DIR'] = __DIR__ . '/logs';
$CONFIG['DEBUG_LOG'] = false;

return $CONFIG;
