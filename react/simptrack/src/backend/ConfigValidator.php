<?php
/**
 * =============================================================================
 * ConfigValidator — Fail-fast schema validation for Simptrack config
 * =============================================================================
 *
 * Runs at the top of every init.php / query.php request. When it detects an
 * inconsistency between the various config sections (FIELD_MAP, SPECIAL_FILTERS,
 * STATIC_DROPDOWNS, DROPDOWN_SOURCES, ROW_ACTIONS, DATA_VIEW schema) it throws
 * ConfigValidationException, which the widget entry points catch and return as
 * HTTP 500 with an actionable JSON body.
 *
 * Rules:
 *   R1 — every dropdown/autocomplete filter has a resolvable optionsKey
 *   R2 — every FIELD_MAP dbColumn exists in DATA_VIEW (cached via INFORMATION_SCHEMA)
 *   R3 — every ROW_ACTIONS.urlTemplate placeholder is a known token
 *   R4 — every SPECIAL_FILTERS.handler is implemented
 *   R5 — every filter.listFilter[0] references a real dbColumn
 */

require_once __DIR__ . '/Logger.php';

class ConfigValidationException extends \RuntimeException
  {
  public string $rule;
  public array $context;

  public function __construct(string $rule, string $message, array $context = [])
    {
    parent::__construct($message);
    $this->rule = $rule;
    $this->context = $context;
    }
  }

class ConfigValidator
  {
  /** Placeholder tokens that mapRow()/buildTrackingLink() emit per row. */
  private const ALLOWED_URL_TOKENS = [
    'BASE_URL',
    'processid',
    'key',
    'username',
    'documentId',
    'incident',
  ];

  /** Handlers currently implemented in query.php::buildWhereClauses(). */
  private const KNOWN_HANDLERS = ['laufzeit', 'coor'];

  private array $config;
  private $jobDB;

  public function __construct(array $config, $jobDB = null)
    {
    $this->config = $config;
    $this->jobDB = $jobDB;
    }

  /** Run all rules. Throws ConfigValidationException on the first failure. */
  public function validate(): void
    {
    $this->validateFilters();
    $this->validateRowActions();
    $this->validateSpecialFilters();
    $this->validateSpvFilter();
    $this->validateRowAuth();
    if ($this->jobDB !== null)
      $this->validateDbColumns();
    }

  // --------------------------------------------------------------------------
  // R1: dropdown/autocomplete optionsKey must resolve
  // R3/R5 pieces that look at FIELD_MAP are also done here for efficiency
  // --------------------------------------------------------------------------
  private function validateFilters(): void
    {
    $static = $this->config['STATIC_DROPDOWNS'] ?? [];
    $sources = $this->config['DROPDOWN_SOURCES'] ?? [];

    foreach ($this->config['FIELD_MAP'] as $field) {
      if (empty($field['filter']))
        continue;
      $filter = $field['filter'];
      $type = $filter['type'] ?? '';

      if ($type === 'dropdown' || $type === 'autocomplete') {
        $optionsKey = $filter['optionsKey'] ?? $filter['key'] ?? null;
        if ($optionsKey === null) {
          throw new ConfigValidationException(
            'R1',
            "Field '{$field['id']}' has a {$type} filter but no 'optionsKey'.",
            ['fieldId' => $field['id'], 'hint' => "Add 'optionsKey' => '<key>' to the filter definition."]
          );
          }
        if (!isset($static[$optionsKey]) && !isset($sources[$optionsKey])) {
          throw new ConfigValidationException(
            'R1',
            "Field '{$field['id']}' filter.optionsKey '{$optionsKey}' is not defined in STATIC_DROPDOWNS or DROPDOWN_SOURCES.",
            [
              'fieldId' => $field['id'],
              'optionsKey' => $optionsKey,
              'hint' => "Add STATIC_DROPDOWNS['{$optionsKey}'] = [...] or DROPDOWN_SOURCES['{$optionsKey}'] = [...].",
              'availableStatic' => array_keys($static),
              'availableSources' => array_keys($sources),
            ]
          );
          }
        }
      }
    }

  // --------------------------------------------------------------------------
  // R3: every urlTemplate placeholder is a known token or a FIELD_MAP id
  // --------------------------------------------------------------------------
  private function validateRowActions(): void
    {
    $fieldIds = array_map(fn($f) => $f['id'], $this->config['FIELD_MAP']);
    $allowed = array_unique(array_merge(self::ALLOWED_URL_TOKENS, $fieldIds));

    foreach ($this->config['ROW_ACTIONS'] as $action) {
      $template = $action['urlTemplate'] ?? '';
      if (!preg_match_all('/\{(\w+)\}/', $template, $matches))
        continue;

      foreach ($matches[1] as $token) {
        if (!in_array($token, $allowed, true)) {
          throw new ConfigValidationException(
            'R3',
            "Row action '{$action['id']}' uses unknown urlTemplate placeholder {{$token}}.",
            [
              'actionId' => $action['id'],
              'token' => $token,
              'allowedTokens' => $allowed,
              'hint' => 'Use a field id from FIELD_MAP, or one of: ' . implode(', ', self::ALLOWED_URL_TOKENS),
            ]
          );
          }
        }
      }
    }

  // --------------------------------------------------------------------------
  // R4: every SPECIAL_FILTERS handler is implemented
  // --------------------------------------------------------------------------
  private function validateSpecialFilters(): void
    {
    foreach (($this->config['SPECIAL_FILTERS'] ?? []) as $filter) {
      $handler = $filter['handler'] ?? '';
      if (!in_array($handler, self::KNOWN_HANDLERS, true)) {
        throw new ConfigValidationException(
          'R4',
          "Special filter '{$filter['id']}' uses handler '{$handler}' which is not implemented.",
          [
            'filterId' => $filter['id'],
            'handler' => $handler,
            'knownHandlers' => self::KNOWN_HANDLERS,
            'hint' => 'Implement the handler in query.php::buildWhereClauses() and add its name to ConfigValidator::KNOWN_HANDLERS.',
          ]
        );
        }
      }
    }

  // --------------------------------------------------------------------------
  // R6: SPV_FILTER (optional) must reference a known DROPDOWN_SOURCES entry
  //     and declare the marker column + value used for tagging options.
  // --------------------------------------------------------------------------
  private function validateSpvFilter(): void
    {
    $spv = $this->config['SPV_FILTER'] ?? null;
    if (empty($spv))
      return;

    foreach (['optionsKey', 'column', 'value'] as $required) {
      if (!array_key_exists($required, $spv) || $spv[$required] === '' || $spv[$required] === null) {
        throw new ConfigValidationException(
          'R6',
          "SPV_FILTER is missing required key '{$required}'.",
          ['hint' => "Set SPV_FILTER['{$required}'] in config.php, or remove SPV_FILTER entirely to disable."]
        );
        }
      }

    $sources = $this->config['DROPDOWN_SOURCES'] ?? [];
    $optionsKey = $spv['optionsKey'];
    if (!isset($sources[$optionsKey])) {
      throw new ConfigValidationException(
        'R6',
        "SPV_FILTER.optionsKey '{$optionsKey}' is not a DROPDOWN_SOURCES entry.",
        [
          'optionsKey' => $optionsKey,
          'availableSources' => array_keys($sources),
          'hint' => 'SPV marking only works on DB-queried dropdowns (DROPDOWN_SOURCES), not STATIC_DROPDOWNS.',
        ]
      );
      }
    }

  // --------------------------------------------------------------------------
  // R7: ROW_AUTH (optional) describes how rows are restricted to the current
  //     user. Accepts modes 'none' | 'equals' | 'list_contains' | 'custom'.
  //     Each mode has its own required keys.
  // --------------------------------------------------------------------------
  private function validateRowAuth(): void
    {
    $auth = $this->config['ROW_AUTH'] ?? null;
    if ($auth === null)
      return; // legacy AUTH_COLUMN path; not validated here

    if (!is_array($auth)) {
      throw new ConfigValidationException(
        'R7',
        "ROW_AUTH must be an array.",
        ['hint' => "Set ROW_AUTH to ['mode' => 'none'] to disable row-level auth."]
      );
      }

    $mode = $auth['mode'] ?? null;
    $allowedModes = ['none', 'equals', 'list_contains', 'custom'];
    if (!in_array($mode, $allowedModes, true)) {
      throw new ConfigValidationException(
        'R7',
        "ROW_AUTH.mode must be one of: " . implode(', ', $allowedModes) . ".",
        ['mode' => $mode, 'hint' => "Use 'none' to disable row-level filtering."]
      );
      }

    if ($mode === 'equals' || $mode === 'list_contains') {
      if (empty($auth['column'])) {
        throw new ConfigValidationException(
          'R7',
          "ROW_AUTH.column is required when mode is '{$mode}'.",
          ['mode' => $mode, 'hint' => "Set ROW_AUTH['column'] to the DATA_VIEW column that holds the access value."]
        );
        }
      }

    if ($mode === 'custom' && empty($auth['sql'])) {
      throw new ConfigValidationException(
        'R7',
        "ROW_AUTH.sql is required when mode is 'custom'.",
        ['hint' => "Provide a SQL fragment in ROW_AUTH['sql']; use '{user}' as the placeholder for the current username."]
      );
      }
    }

  // --------------------------------------------------------------------------
  // R2 + R5: every FIELD_MAP dbColumn and filter.listFilter[0] exists in DATA_VIEW
  // Cached 10 min in src/backend/cache/schema.json
  // --------------------------------------------------------------------------
  private function validateDbColumns(): void
    {
    $columns = $this->getDataViewColumns();
    if ($columns === null)
      return; // schema lookup failed — non-fatal, logged

    $needed = [];
    foreach ($this->config['FIELD_MAP'] as $field) {
      if (!empty($field['dbColumn']))
        $needed[strtolower($field['dbColumn'])] = ['field' => $field['id'], 'source' => 'dbColumn'];
      if (!empty($field['filter']['dbColumn']))
        $needed[strtolower($field['filter']['dbColumn'])] = ['field' => $field['id'], 'source' => 'filter.dbColumn'];
      if (!empty($field['filter']['listFilter'][0]))
        $needed[strtolower($field['filter']['listFilter'][0])] = ['field' => $field['id'], 'source' => 'filter.listFilter'];
      }

    foreach ($needed as $col => $info) {
      if (!in_array($col, $columns, true)) {
        throw new ConfigValidationException(
          'R2',
          "Column '{$col}' ({$info['source']} for field '{$info['field']}') does not exist in DATA_VIEW '{$this->config['DATA_VIEW']}'.",
          [
            'fieldId' => $info['field'],
            'column' => $col,
            'source' => $info['source'],
            'dataView' => $this->config['DATA_VIEW'],
            'availableColumns' => $columns,
            'hint' => "Check the spelling, or update FIELD_MAP to match DATA_VIEW's actual columns.",
          ]
        );
        }
      }
    }

  /** Returns lowercase column names of DATA_VIEW, or null if lookup failed. */
  private function getDataViewColumns(): ?array
    {
    $cacheFile = __DIR__ . '/cache/schema.json';
    $ttl = 600;
    $dataView = $this->config['DATA_VIEW'];

    if (is_file($cacheFile)) {
      $age = time() - @filemtime($cacheFile);
      if ($age < $ttl) {
        $cached = json_decode(@file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached[$dataView]) && is_array($cached[$dataView]))
          return $cached[$dataView];
        }
      }

    try {
      $safe = addslashes($dataView);
      $query = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$safe}'";
      $result = $this->jobDB->query($query);
      $cols = [];
      while ($row = $this->jobDB->fetchRow($result)) {
        $name = $row['COLUMN_NAME'] ?? $row['column_name'] ?? null;
        if ($name !== null)
          $cols[] = strtolower($name);
        }
      if (empty($cols)) {
        Logger::warn('schema.empty', ['dataView' => $dataView]);
        return null;
        }

      $cacheDir = dirname($cacheFile);
      if (!is_dir($cacheDir))
        @mkdir($cacheDir, 0755, true);
      $existing = is_file($cacheFile) ? (json_decode(@file_get_contents($cacheFile), true) ?: []) : [];
      $existing[$dataView] = $cols;
      @file_put_contents($cacheFile, json_encode($existing));

      return $cols;
      } catch (\Throwable $e) {
      Logger::warn('schema.lookup_failed', ['dataView' => $dataView, 'error' => $e->getMessage()]);
      return null;
      }
    }
  }
