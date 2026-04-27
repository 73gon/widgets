<?php
/**
 * =============================================================================
 * ConfigNormalizer — Expand compact configs into the full Simptrack schema
 * =============================================================================
 *
 * Customer config files may use a compact shorthand for the most common cases.
 * This normalizer is run once at request entry (init.php / query.php) before
 * ConfigValidator and the rest of the pipeline see the config, and expands
 * the shorthand into the verbose internal shape that the rest of the codebase
 * already understands.
 *
 * Compact features supported:
 *
 *   1. String shorthand for filters:
 *        'filter' => 'text'         → text_like LIKE '%…%'
 *        'filter' => 'autocomplete' → multi-select autocomplete (listFilter)
 *        'filter' => 'dropdown'     → single-select dropdown
 *        'filter' => 'date'         → single date equality
 *        'filter' => 'daterange'    → from/to date range
 *        'filter' => 'numberrange'  → from/to number range
 *
 *   2. Auto-derived field defaults:
 *        - id      defaults to lcfirst(camelCase(dbColumn))
 *        - label   defaults to id
 *        - type    defaults to 'text'
 *        - align   defaults to 'left'
 *
 *   3. Auto-derived static dropdowns:
 *        - 'status'   injected if any field has type 'status' and STATIC_DROPDOWNS['status'] is missing
 *        - 'laufzeit' derived from LAUFZEIT_RANGES keys when missing
 *
 *   4. Defaults for optional top-level keys (DEFAULTS array below).
 *
 * The verbose shape (today's syntax) still works unchanged — anything already
 * in array form passes through.
 */

class ConfigNormalizer
  {
  /** Default top-level config values applied when a key is missing. */
  private const DEFAULTS = [
    'DB_TYPE' => 'auto',
    'PREFERENCES_TABLE' => 'WIDGET_SIMPTRACK',
    'CACHE_TTL' => 600,
    'DEBUG_LOG' => false,
    'ROW_AUTH' => ['mode' => 'none'],
    'THEME' => ['primaryColor' => '#ffcc00', 'defaultMode' => 'dark'],
    'LOCALE' => ['language' => 'de-DE', 'currency' => 'EUR'],
    'STATUS_COLUMN' => '',
    'ESCALATION_COLUMN' => '',
    'STATUS_LABELS' => [
      'completed' => 'Beendet',
      'due' => 'Fällig',
      'not_due' => 'Nicht fällig',
    ],
    'COMPUTED_FIELDS' => [],
    'DROPDOWN_SOURCES' => [],
    'STATIC_DROPDOWNS' => [],
    'SPECIAL_FILTERS' => [],
    'ROW_ACTIONS' => [],
    'LAUFZEIT_RANGES' => [],
  ];

  /**
   * Returns a fully-expanded copy of $config.
   * Idempotent: running on already-verbose config is a no-op.
   */
  public static function normalize(array $config): array
    {
    // 1. Apply top-level defaults.
    foreach (self::DEFAULTS as $key => $default) {
      if (!array_key_exists($key, $config)) {
        $config[$key] = $default;
        }
      }

    // LOG_DIR default is path-relative to the calling backend folder.
    if (!array_key_exists('LOG_DIR', $config)) {
      $config['LOG_DIR'] = __DIR__ . '/logs';
      }

    // 2. Expand FIELD_MAP entries.
    if (!empty($config['FIELD_MAP']) && is_array($config['FIELD_MAP'])) {
      $dropdownSources = $config['DROPDOWN_SOURCES'] ?? [];
      $config['FIELD_MAP'] = array_map(
        fn($field) => self::expandField($field, $dropdownSources),
        $config['FIELD_MAP']
      );
      }

    // 3. Auto-inject STATIC_DROPDOWNS that the widget needs but the customer
    //    didn't bother to spell out.
    $config['STATIC_DROPDOWNS'] = self::injectStaticDropdowns(
      $config['STATIC_DROPDOWNS'] ?? [],
      $config['FIELD_MAP'] ?? [],
      $config['LAUFZEIT_RANGES'] ?? []
    );

    return $config;
    }

  /**
   * Expand one FIELD_MAP entry, applying defaults and unfolding the compact
   * 'filter' string form. $dropdownSources is consulted by the 'autocomplete'
   * shorthand to find the SQL value column to filter on.
   */
  private static function expandField(array $field, array $dropdownSources = []): array
    {
    // Action / non-data fields don't need expansion.
    if (($field['type'] ?? '') === 'actions') {
      $field['id'] ??= 'actions';
      $field['label'] ??= '';
      $field['align'] ??= 'center';
      return $field;
      }

    $dbColumn = $field['dbColumn'] ?? '';
    if (!isset($field['id']) && $dbColumn !== '') {
      $field['id'] = self::camelCase($dbColumn);
      }
    $field['label'] ??= $field['id'] ?? '';
    $field['type'] ??= 'text';
    $field['align'] ??= 'left';

    if (isset($field['filter']) && is_string($field['filter'])) {
      $field['filter'] = self::expandFilterShorthand($field['filter'], $field, $dropdownSources);
      }

    return $field;
    }

  /**
   * Expand a string filter shorthand into the full filter array, deriving
   * keys / dbColumns / range ids from the field's own id + dbColumn.
   */
  private static function expandFilterShorthand(string $shorthand, array $field, array $dropdownSources = []): array
    {
    $id = $field['id'] ?? '';
    $label = $field['label'] ?? $id;
    $dbColumn = $field['dbColumn'] ?? '';

    switch ($shorthand) {
      case 'text':
        return [
          'id' => $id,
          'label' => $label,
          'type' => 'text',
          'key' => $id,
          'dbColumn' => $dbColumn,
          'dbType' => 'text_like',
          'defaultValue' => '',
        ];

      case 'autocomplete':
        // Multi-select autocomplete filters issue an SQL IN(…) clause against
        // the value column of the matching DROPDOWN_SOURCES entry. Falls back
        // to the field's own dbColumn when no source is registered.
        $source = $dropdownSources[$id] ?? null;
        $valueCol = $source['valueCol'] ?? $dbColumn;
        $castToInt = $source['castToInt'] ?? true;
        return [
          'id' => $id,
          'label' => $label,
          'type' => 'autocomplete',
          'key' => $id,
          'defaultValue' => [],
          'optionsKey' => $id,
          'listFilter' => [$valueCol, (bool) $castToInt],
        ];

      case 'dropdown':
        return [
          'id' => $id,
          'label' => $label,
          'type' => 'dropdown',
          'key' => $id,
          'dbColumn' => $dbColumn,
          'dbType' => 'equality',
          'defaultValue' => 'all',
          'optionsKey' => $id,
        ];

      case 'date':
        return [
          'id' => $id,
          'label' => $label,
          'type' => 'date',
          'key' => $id,
          'dbColumn' => $dbColumn,
          'dbType' => 'date_gte',
          'defaultValue' => '',
        ];

      case 'daterange':
        return [
          'id' => $id,
          'label' => $label,
          'type' => 'daterange',
          'key' => $id,
          'defaultValue' => '',
          'rangeIds' => [$id . 'From', $id . 'To'],
          'rangeDbTypes' => ['date_gte', 'date_lte'],
          'dbColumn' => $dbColumn,
        ];

      case 'numberrange':
        return [
          'id' => $id,
          'label' => $label,
          'type' => 'numberrange',
          'key' => $id,
          'defaultValue' => '',
          'rangeIds' => [$id . 'From', $id . 'To'],
          'rangeDbTypes' => ['number_gte', 'number_lte'],
          'dbColumn' => $dbColumn,
        ];

      case 'boolean':
        return [
          'id' => $id,
          'label' => $label,
          'type' => 'dropdown',
          'key' => $id,
          'dbColumn' => $dbColumn,
          'dbType' => 'boolean_10',
          'defaultValue' => 'all',
          'optionsKey' => $id,
        ];

      default:
        // Unknown shorthand — fall back to text and let validator complain
        // with a clearer message downstream.
        return [
          'id' => $id,
          'label' => $label,
          'type' => $shorthand,
          'key' => $id,
          'dbColumn' => $dbColumn,
          'defaultValue' => '',
        ];
      }
    }

  /**
   * Inject STATIC_DROPDOWNS the runtime relies on but the customer didn't
   * spell out (status options, laufzeit ranges).
   */
  private static function injectStaticDropdowns(array $static, array $fieldMap, array $laufzeitRanges): array
    {
    $hasStatusField = false;
    foreach ($fieldMap as $field) {
      if (($field['type'] ?? '') === 'status') {
        $hasStatusField = true;
        break;
        }
      }
    if ($hasStatusField && !isset($static['status'])) {
      $static['status'] = [
        ['id' => 'completed', 'label' => 'Beendet'],
        ['id' => 'aktiv_alle', 'label' => 'Aktiv Alle'],
        ['id' => 'faellig', 'label' => 'Aktiv Fällig'],
        ['id' => 'not_faellig', 'label' => 'Aktiv Nicht Fällig'],
      ];
      }

    if (!empty($laufzeitRanges) && !isset($static['laufzeit'])) {
      $static['laufzeit'] = [];
      foreach (array_keys($laufzeitRanges) as $label) {
        $static['laufzeit'][] = ['id' => $label, 'label' => $label];
        }
      }

    return $static;
    }

  /** "DW_DokumentID" → "dwDokumentID", "Mandant" → "mandant", "user_name" → "userName". */
  private static function camelCase(string $input): string
    {
    $clean = preg_replace('/[^A-Za-z0-9]+/', ' ', $input);
    $parts = preg_split('/\s+/', trim($clean));
    if (empty($parts)) {
      return $input;
      }
    $first = lcfirst(array_shift($parts));
    $rest = array_map('ucfirst', $parts);
    return $first . implode('', $rest);
    }
  }
