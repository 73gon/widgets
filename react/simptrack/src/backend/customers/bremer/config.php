<?php
/**
 * =============================================================================
 * Simptrack Widget — Customer Configuration: BREMER
 * =============================================================================
 *
 * Wraps the customer's invoice/process SQL view ER_VORGANG, which joins
 * dbo.JRINCIDENT + dbo.LASTSTEP + dbo.RE_KOPF (latest LASTSTEP per process).
 *
 * Uses the compact field syntax — see src/backend/config.php for the
 * full reference.
 */

$CONFIG = [];

// =============================================================================
// 1. DATABASE
// =============================================================================

$CONFIG['DATA_VIEW'] = 'ER_VORGANG';

// =============================================================================
// 2. URLS & SECRETS
// =============================================================================

$CONFIG['BASE_URL'] = 'https://jobrouter.bremerbau.local/jobrouter';

/** TODO: replace with the real JobRouter tracking passphrase from Bremer's instance. */
$CONFIG['TRACKING_PASSPHRASE'] = 'change-me-bremer';

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
  ['id' => 'actions', 'type' => 'actions'],

  ['id' => 'incident', 'label' => 'Vorgangsnummer', 'dbColumn' => 'incident', 'filter' => 'text'],
  ['id' => 'mandant', 'label' => 'Mandant', 'dbColumn' => 'Mandant', 'filter' => 'text'],

  ['id' => 'entryDate', 'label' => 'Eingangsdatum', 'type' => 'date', 'dbColumn' => 'Eingangsdatum'],
  ['id' => 'bookingDate', 'label' => 'Buchungsdatum', 'type' => 'date', 'dbColumn' => 'Buchungsdatum'],

  ['id' => 'stepLabel', 'label' => 'Schritt', 'dbColumn' => 'steplabel', 'filter' => 'autocomplete'],
  ['id' => 'startDate', 'label' => 'Startdatum (Schritt)', 'type' => 'date', 'dbColumn' => 'indate'],
  ['id' => 'fullName', 'label' => 'Bearbeiter', 'dbColumn' => 'username', 'filter' => 'text'],

  ['id' => 'creditorNumber', 'label' => 'Kreditor-Nr.', 'dbColumn' => 'Kreditorennummer', 'filter' => 'text'],
  ['id' => 'creditorName', 'label' => 'Kreditor', 'dbColumn' => 'KREDITORENNAME', 'filter' => 'text'],

  ['id' => 'documentNumber', 'label' => 'Dokumenten-ID', 'dbColumn' => 'DW_DokumentID'],
  ['id' => 'invoiceNumber', 'label' => 'Rechnungsnummer', 'dbColumn' => 'Externe_Belegnummer', 'filter' => 'text'],
  ['id' => 'invoiceDate', 'label' => 'Belegdatum', 'type' => 'date', 'dbColumn' => 'Belegdatum', 'filter' => 'daterange'],

  ['id' => 'netAmount', 'label' => 'Nettobetrag', 'type' => 'currency', 'dbColumn' => 'Nettobetrag', 'filter' => 'numberrange'],

  ['id' => 'dueDate', 'label' => 'Fälligkeit', 'type' => 'date', 'dbColumn' => 'Faelligkeitsdatum'],
  ['id' => 'cashDiscountDueDate', 'label' => 'Skontofälligkeit', 'type' => 'date', 'dbColumn' => 'Skontofaelligkeit'],
  ['id' => 'cashDiscountAmount', 'label' => 'Skontobetrag', 'type' => 'currency', 'dbColumn' => 'Skontobetrag'],

  ['id' => 'costCenter', 'label' => 'Kostenstelle', 'dbColumn' => 'Kostenstelle', 'filter' => 'text'],
  ['id' => 'invoiceKind', 'label' => 'Rechnungsart', 'dbColumn' => 'RECHNUNGSART', 'filter' => 'dropdown'],
  ['id' => 'invoiceType', 'label' => 'Rechnungstyp', 'dbColumn' => 'RECHNUNGSTYP', 'filter' => 'dropdown'],
  ['id' => 'documentKind', 'label' => 'Belegart', 'dbColumn' => 'JOBSELECT_BELEGART', 'filter' => 'dropdown'],
  ['id' => 'downPayment', 'label' => 'Anzahlung', 'dbColumn' => 'Anzahlungsvorgang'],
];

// =============================================================================
// 5. ROW ACTIONS
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
// 6. DROPDOWN SOURCES (DB-queried option lists)
// =============================================================================

$CONFIG['DROPDOWN_SOURCES'] = [
  'stepLabel' => ['table' => 'DATA_VIEW', 'valueCol' => 'step', 'labelCol' => 'steplabel', 'distinct' => true],
  'invoiceKind' => ['table' => 'DATA_VIEW', 'valueCol' => 'RECHNUNGSART', 'labelCol' => 'RECHNUNGSART', 'distinct' => true],
  'invoiceType' => ['table' => 'DATA_VIEW', 'valueCol' => 'RECHNUNGSTYP', 'labelCol' => 'RECHNUNGSTYP', 'distinct' => true],
  'documentKind' => ['table' => 'DATA_VIEW', 'valueCol' => 'JOBSELECT_BELEGART', 'labelCol' => 'JOBSELECT_BELEGART', 'distinct' => true],
];

return $CONFIG;
