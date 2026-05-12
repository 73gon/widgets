<?php
/**
 * =============================================================================
 * Simptrack Widget — Customer Configuration: BREMER
 * =============================================================================
 */

$CONFIG = [];

// =============================================================================
// 1. DATABASE
// =============================================================================

$CONFIG['DB_TYPE'] = 'mysql';

/** Customer-specific view wrapping JRINCIDENT + LASTSTEP + RE_KOPF. */
$CONFIG['DATA_VIEW'] = 'V_UEBERSICHTEN_WIDGET';

$CONFIG['PREFERENCES_TABLE'] = 'WIDGET_SIMPTRACK';

$CONFIG['ROW_AUTH'] = [
  'mode' => 'none',
];

$CONFIG['EXPORT_MAX_ROWS'] = 5000;
// =============================================================================
// 2. URLS & SECRETS
// =============================================================================

$CONFIG['BASE_URL'] = 'https://jobrequest.cdmsmith.com/jobrouter';
$CONFIG['TRACKING_PASSPHRASE'] = 'Salomon99$';

// =============================================================================
// 3. THEME
// =============================================================================

$CONFIG['THEME'] = [
  'primaryColor' => '#ffcc00',
  'defaultMode' => 'dark',
];

// =============================================================================
// 4. FIELD MAP
// =============================================================================

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
    'id' => 'mandant',
    'label' => 'Mandant',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'mandantname',
    'filter' => ['id' => 'mandant', 'label' => 'Mandant', 'type' => 'text', 'key' => 'mandant', 'dbColumn' => 'mandantname', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'mandantNumber',
    'label' => 'Mandanten-Nr.',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'mandantnr',
  ],
  [
    'id' => 'entryDate',
    'label' => 'Eingangsdatum',
    'type' => 'date',
    'align' => 'left',
    'dbColumn' => 'EINGANGSDATUM',
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
    'filter' => ['id' => 'bearbeiter', 'label' => 'Bearbeiter', 'type' => 'text', 'key' => 'bearbeiter', 'dbColumn' => 'username', 'dbType' => 'text_like', 'defaultValue' => ''],
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
    'id' => 'documentNumber',
    'label' => 'Dokumenten-ID',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'dokumentid',
    'filter' => ['id' => 'belegnr', 'label' => 'Beleg-Nr.', 'type' => 'text', 'key' => 'belegnr', 'dbColumn' => 'dokumentid', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'invoiceNumber',
    'label' => 'Rechnungsnummer',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'RECHNUNGSNUMMER',
    'filter' => ['id' => 'rechnungsnummer', 'label' => 'Rechnungsnummer', 'type' => 'text', 'key' => 'rechnungsnummer', 'dbColumn' => 'RECHNUNGSNUMMER', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'invoiceDate',
    'label' => 'Rechnungsdatum',
    'type' => 'date',
    'align' => 'left',
    'dbColumn' => 'RECHNUNGSDATUM',
    'filter' => [
      'id' => 'rechnungsdatum',
      'label' => 'Rechnungsdatum',
      'type' => 'daterange',
      'key' => 'rechnungsdatum',
      'defaultValue' => '',
      'rangeIds' => ['rechnungsdatumFrom', 'rechnungsdatumTo'],
      'rangeDbTypes' => ['date_gte', 'date_lte'],
      'dbColumn' => 'RECHNUNGSDATUM',
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
    'id' => 'zahlbetrag',
    'label' => 'Zahlbetrag',
    'type' => 'currency',
    'align' => 'left',
    'dbColumn' => 'zahlbetrag',
  ],
  [
    'id' => 'dueDate',
    'label' => 'Fälligkeit',
    'type' => 'date',
    'align' => 'left',
    'dbColumn' => 'faelligkeit',
  ],
  [
    'id' => 'invoiceDueDate',
    'label' => 'Rechnungsfälligkeit',
    'type' => 'date',
    'align' => 'left',
    'dbColumn' => 'rechnungsfaelligkeit',
  ],
  [
    'id' => 'invoiceType',
    'label' => 'Rechnungstyp',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'rechnungstyp',
    'filter' => ['type' => 'dropdown', 'key' => 'rechnungstyp', 'defaultValue' => 'all', 'optionsKey' => 'rechnungstyp'],
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

$CONFIG['STATUS_COLUMN'] = 'status';
$CONFIG['ESCALATION_COLUMN'] = 'ESKALATION';

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
  'rechnungstyp' => [
    'table' => 'DATA_VIEW',
    'valueCol' => 'rechnungstyp',
    'labelCol' => 'rechnungstyp',
    'distinct' => true,
  ],
];

// =============================================================================
// 7. STATIC DROPDOWNS
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

$CONFIG['CACHE_TTL'] = 21600; // 6 hours; dropdown values come from a slow reporting view.

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
$CONFIG['DEBUG_LOG'] = true;

return $CONFIG;
