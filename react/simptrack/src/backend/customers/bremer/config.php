<?php
/**
 * =============================================================================
 * Simptrack Widget — Customer Configuration: BREMER
 * =============================================================================
 *
 * Generated from the customer's invoice/process SQL view, which joins:
 *   dbo.JRINCIDENT (JRI)  +  dbo.LASTSTEP  +  dbo.RE_KOPF
 *
 * The DATA_VIEW below should point to a SQL Server view that wraps the join
 * shown in the customer's source query (filtered by LASTSTEP.indate = MAX).
 *
 * Field IDs use simptrack's neutral naming; dbColumn values map to the
 * customer's actual columns (SQL Server is case-insensitive).
 */

$CONFIG = [];

// =============================================================================
// 1. DATABASE
// =============================================================================

$CONFIG['DB_TYPE'] = 'auto';

/** Customer-specific view wrapping JRINCIDENT + LASTSTEP + RE_KOPF. */
$CONFIG['DATA_VIEW'] = 'ER_VORGANG';

$CONFIG['PREFERENCES_TABLE'] = 'WIDGET_SIMPTRACK';

$CONFIG['ROW_AUTH'] = [
  'mode' => 'none',
];

// =============================================================================
// 2. URLS & SECRETS
// =============================================================================

$CONFIG['BASE_URL'] = 'https://jobrouter.bremerbau.local/jobrouter';
$CONFIG['TRACKING_PASSPHRASE'] = 'F8PYN48WCD]Ug^H]';

// =============================================================================
// 3. THEME
// =============================================================================

$CONFIG['THEME'] = [
  'primaryColor' => '#ffcc00',
  'defaultMode' => 'light',
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
    'dbColumn' => 'Mandant',
    'filter' => ['id' => 'mandant', 'label' => 'Mandant', 'type' => 'text', 'key' => 'mandant', 'dbColumn' => 'Mandant', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'entryDate',
    'label' => 'Eingangsdatum',
    'type' => 'date',
    'align' => 'left',
    'dbColumn' => 'Eingangsdatum',
  ],
  [
    'id' => 'bookingDate',
    'label' => 'Buchungsdatum',
    'type' => 'date',
    'align' => 'left',
    'dbColumn' => 'Buchungsdatum',
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
    'dbColumn' => 'username',
    'filter' => ['id' => 'bearbeiter', 'label' => 'Bearbeiter', 'type' => 'text', 'key' => 'bearbeiter', 'dbColumn' => 'username', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'creditorNumber',
    'label' => 'Kreditor-Nr.',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'Kreditorennummer',
    'filter' => ['id' => 'kreditornr', 'label' => 'Kreditor-Nr.', 'type' => 'text', 'key' => 'kreditornr', 'dbColumn' => 'Kreditorennummer', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'creditorName',
    'label' => 'Kreditor',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'KREDITORENNAME',
    'filter' => ['id' => 'kreditor', 'label' => 'Kreditor', 'type' => 'text', 'key' => 'kreditor', 'dbColumn' => 'KREDITORENNAME', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'documentNumber',
    'label' => 'Dokumenten-ID',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'DW_DokumentID',
    'filter' => ['id' => 'belegnr', 'label' => 'Beleg-Nr.', 'type' => 'text', 'key' => 'belegnr', 'dbColumn' => 'Nr', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'invoiceNumber',
    'label' => 'Rechnungsnummer',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'Externe_Belegnummer',
    'filter' => ['id' => 'rechnungsnummer', 'label' => 'Rechnungsnummer', 'type' => 'text', 'key' => 'rechnungsnummer', 'dbColumn' => 'Externe_Belegnummer', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'invoiceDate',
    'label' => 'Belegdatum',
    'type' => 'date',
    'align' => 'left',
    'dbColumn' => 'Belegdatum',
    'filter' => [
      'id' => 'belegdatum',
      'label' => 'Belegdatum',
      'type' => 'daterange',
      'key' => 'belegdatum',
      'defaultValue' => '',
      'rangeIds' => ['belegdatumFrom', 'belegdatumTo'],
      'rangeDbTypes' => ['date_gte', 'date_lte'],
      'dbColumn' => 'Belegdatum',
    ],
  ],
  [
    'id' => 'netAmount',
    'label' => 'Nettobetrag',
    'type' => 'currency',
    'align' => 'left',
    'dbColumn' => 'Nettobetrag',
    'filter' => [
      'id' => 'nettobetrag',
      'label' => 'Nettobetrag',
      'type' => 'numberrange',
      'key' => 'nettobetrag',
      'defaultValue' => '',
      'rangeIds' => ['nettobetragFrom', 'nettobetragTo'],
      'rangeDbTypes' => ['number_gte', 'number_lte'],
      'dbColumn' => 'Nettobetrag',
    ],
  ],
  [
    'id' => 'dueDate',
    'label' => 'Fälligkeit',
    'type' => 'date',
    'align' => 'left',
    'dbColumn' => 'Faelligkeitsdatum',
  ],
  [
    'id' => 'cashDiscountDueDate',
    'label' => 'Skontofälligkeit',
    'type' => 'date',
    'align' => 'left',
    'dbColumn' => 'Skontofaelligkeit',
  ],
  [
    'id' => 'cashDiscountAmount',
    'label' => 'Skontobetrag',
    'type' => 'currency',
    'align' => 'left',
    'dbColumn' => 'Skontobetrag',
  ],
  [
    'id' => 'costCenter',
    'label' => 'Kostenstelle',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'Kostenstelle',
    'filter' => ['id' => 'kostenstelle', 'label' => 'Kostenstelle', 'type' => 'text', 'key' => 'kostenstelle', 'dbColumn' => 'Kostenstelle', 'dbType' => 'text_like', 'defaultValue' => ''],
  ],
  [
    'id' => 'invoiceKind',
    'label' => 'Rechnungsart',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'RECHNUNGSART',
    'filter' => ['type' => 'dropdown', 'key' => 'rechnungsart', 'defaultValue' => 'all', 'optionsKey' => 'rechnungsart'],
  ],
  [
    'id' => 'invoiceType',
    'label' => 'Rechnungstyp',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'RECHNUNGSTYP',
    'filter' => ['type' => 'dropdown', 'key' => 'rechnungstyp', 'defaultValue' => 'all', 'optionsKey' => 'rechnungstyp'],
  ],
  [
    'id' => 'documentKind',
    'label' => 'Belegart',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'JOBSELECT_BELEGART',
    'filter' => ['type' => 'dropdown', 'key' => 'belegart', 'defaultValue' => 'all', 'optionsKey' => 'belegart'],
  ],
  [
    'id' => 'downPayment',
    'label' => 'Anzahlung',
    'type' => 'text',
    'align' => 'left',
    'dbColumn' => 'Anzahlungsvorgang',
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

$CONFIG['STATUS_COLUMN'] = '';
$CONFIG['ESCALATION_COLUMN'] = 'Faelligkeitsdatum';

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
  'rechnungsart' => [
    'table' => 'DATA_VIEW',
    'valueCol' => 'RECHNUNGSART',
    'labelCol' => 'RECHNUNGSART',
    'distinct' => true,
  ],
  'rechnungstyp' => [
    'table' => 'DATA_VIEW',
    'valueCol' => 'RECHNUNGSTYP',
    'labelCol' => 'RECHNUNGSTYP',
    'distinct' => true,
  ],
  'belegart' => [
    'table' => 'DATA_VIEW',
    'valueCol' => 'JOBSELECT_BELEGART',
    'labelCol' => 'JOBSELECT_BELEGART',
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
    'id' => 'document',
    'label' => 'Beleg anzeigen',
    'icon' => 'document',
    'enabled' => true,
    'urlTemplate' => '{BASE_URL}/index.php?cmd=Document_Show&docid={documentNumber}&display=popup',
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
