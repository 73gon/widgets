<?php

namespace dashboard\MyWidgets\SimplifyTable;

use JobRouter\Api\Dashboard\v1\Widget;
use Exception;
use Throwable;

require_once(__DIR__ . '/../../../includes/central.php');
require_once(__DIR__ . '/DatabaseHelper.php');
require_once(__DIR__ . '/ConfigValidator.php');
require_once(__DIR__ . '/Logger.php');

class Query extends Widget
  {
  private array $config;
  private \DatabaseHelper $dbHelper;
  /** Most recent SQL — logged on exception for diagnostics. */
  private ?string $lastSql = null;

  public function __construct()
    {
    $this->config = require __DIR__ . '/config.php';
    \Logger::configure($this->config['LOG_DIR'] ?? __DIR__ . '/logs');
    }

  public function getTitle(): string
    {
    return 'SimplifyTable Query';
    }

  // =============================================
  // CONFIG-DERIVED REGISTRIES
  // =============================================

  /** Frontend column ID → DB column name */
  private function getFieldMap(): array
    {
    $map = [];
    foreach ($this->config['FIELD_MAP'] as $field) {
      if (!empty($field['dbColumn'])) {
        $map[$field['id']] = $field['dbColumn'];
        }
      }
    return $map;
    }

  /**
   * Standard filter definitions from config FIELD_MAP.
   * Returns: filterKey => [dbColumn, filterType]
   */
  private function getFilterDefs(): array
    {
    $defs = [];
    foreach ($this->config['FIELD_MAP'] as $field) {
      if (empty($field['filter']))
        continue;
      $filter = $field['filter'];

      // Skip multi-select (list) filters — handled by getListFilterDefs
      if (!empty($filter['listFilter']))
        continue;
      // status is a special filter with its own SQL
      if (($filter['key'] ?? '') === 'status')
        continue;

      if (!empty($filter['rangeIds'])) {
        $rangeIds = $filter['rangeIds'];
        $rangeDbTypes = $filter['rangeDbTypes'] ?? [];
        $dbCol = $filter['dbColumn'] ?? $field['dbColumn'] ?? '';
        if (isset($rangeIds[0]) && isset($rangeDbTypes[0])) {
          $defs[$rangeIds[0]] = [$dbCol, $rangeDbTypes[0]];
          }
        if (isset($rangeIds[1]) && isset($rangeDbTypes[1])) {
          $defs[$rangeIds[1]] = [$dbCol, $rangeDbTypes[1]];
          }
        } else {
        $dbCol = $filter['dbColumn'] ?? $field['dbColumn'] ?? '';
        $dbType = $filter['dbType'] ?? 'text_like';
        $defs[$filter['key']] = [$dbCol, $dbType];
        }
      }
    return $defs;
    }

  /** Multi-select IN-list filters. Returns: filterKey => [dbColumn, castToInt] */
  private function getListFilterDefs(): array
    {
    $defs = [];
    foreach ($this->config['FIELD_MAP'] as $field) {
      if (empty($field['filter']) || empty($field['filter']['listFilter']))
        continue;
      $filter = $field['filter'];
      $defs[$filter['key']] = $filter['listFilter'];
      }
    return $defs;
    }

  /** Frontend field ids whose filter.dbType indicates a 0/1 boolean column. */
  private function getBooleanFieldIds(): array
    {
    $ids = [];
    foreach ($this->config['FIELD_MAP'] as $field) {
      if (!empty($field['filter']) && ($field['filter']['dbType'] ?? '') === 'boolean_10') {
        $ids[$field['id']] = $field['dbColumn'] ?? '';
        }
      }
    return $ids;
    }

  private function getDbHelper(): \DatabaseHelper
    {
    if (!isset($this->dbHelper)) {
      $this->dbHelper = new \DatabaseHelper($this->getJobDB(), $this->config['DB_TYPE']);
      }
    return $this->dbHelper;
    }

  public static function execute(): void
    {
    $widget = null;
    try {
      $widget = new static();
      $response = $widget->handleRequest();
      header('Content-Type: application/json');
      echo json_encode($response);
      } catch (\ConfigValidationException $e) {
      \Logger::error('config.validation', [
        'rule' => $e->rule,
        'message' => $e->getMessage(),
        'context' => $e->context,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
      ]);
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode([
        'error' => $e->getMessage(),
        'rule' => $e->rule,
        'hint' => $e->context['hint'] ?? null,
        'details' => $e->context,
      ]);
      } catch (Exception $e) {
      \Logger::error('query.exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'sql' => $widget?->lastSql,
        'trace' => $e->getTraceAsString(),
      ]);
      http_response_code(500);
      echo json_encode(['error' => 'Exception: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
      } catch (Throwable $e) {
      \Logger::error('query.throwable', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'sql' => $widget?->lastSql,
        'trace' => $e->getTraceAsString(),
      ]);
      http_response_code(500);
      echo json_encode(['error' => 'Error: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
      }
    }

  private function getParam(string $key, $default = '')
    {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
    }

  private function runSql(string $sql)
    {
    $this->lastSql = $sql;
    if (!empty($this->config['DEBUG_LOG'])) {
      \Logger::debug('query.sql', ['sql' => $sql]);
      }
    return $this->getJobDB()->query($sql);
    }

  private function handleRequest(): array
    {
    (new \ConfigValidator($this->config, $this->getJobDB()))->validate();

    $page = max(1, (int) $this->getParam('page', 1));
    $perPage = max(1, min(100, (int) $this->getParam('perPage', 25)));
    $offset = ($page - 1) * $perPage;
    $export = $this->getParam('export', '') === '1';

    $sortColumn = $this->getParam('sortColumn', '');
    if ($sortColumn === 'historyLink') {
      $sortColumn = '';
      }
    $sortDirection = strtolower($this->getParam('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';

    if (empty($sortColumn)) {
      $sortColumn = 'invoiceDate';
      $sortDirection = 'desc';
      }

    $username = $this->getParam('username', '');

    // Read all standard filter params from registry
    $filters = [];
    foreach (array_keys($this->getFilterDefs()) as $key) {
      $filters[$key] = $this->getParam($key, '');
      }
    // Special filters handled with custom WHERE logic
    $filters['status'] = $this->getParam('status', '');
    $filters['laufzeit'] = $this->getParam('laufzeit', '');
    $filters['coor'] = $this->getParam('coor', '');

    // Multi-select list filters from registry
    $listFilters = [];
    foreach (array_keys($this->getListFilterDefs()) as $key) {
      $listFilters[$key] = $this->decodeListParam($this->getParam($key, ''));
      }

    // Sort map = field map + status (status is computed but sortable by raw DB column)
    $sortMap = $this->getFieldMap();
    $sortMap['status'] = $this->config['STATUS_COLUMN'];

    $orderSql = '';
    if (!empty($sortColumn) && array_key_exists($sortColumn, $sortMap)) {
      $orderSql = 'ORDER BY ' . $sortMap[$sortColumn] . ' ' . $sortDirection;
      } else {
      $orderSql = 'ORDER BY (SELECT NULL)';
      }

    $where = $this->buildWhereClauses($filters, $username, $listFilters);
    $whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

    $dataView = $this->config['DATA_VIEW'];

    $countQuery = "SELECT COUNT(*) as total FROM {$dataView} {$whereSql}";
    $countResult = $this->runSql($countQuery);
    $totalRow = $this->getJobDB()->fetchRow($countResult);
    $total = $totalRow ? (int) $totalRow['total'] : 0;

    $dbHelper = $this->getDbHelper();

    if ($export) {
      $dataQuery = "SELECT * FROM {$dataView} {$whereSql} {$orderSql}";
      } else {
      $baseQuery = "SELECT * FROM {$dataView} {$whereSql} {$orderSql}";
      $dataQuery = $dbHelper->paginateQuery($baseQuery, $offset, $perPage);
      }
    $result = $this->runSql($dataQuery);

    $booleanFields = $this->getBooleanFieldIds();
    $data = [];
    while ($row = $this->getJobDB()->fetchRow($result)) {
      $data[] = $this->mapRow($row, $username, $booleanFields);
      }

    return [
      'page' => $page,
      'perPage' => $perPage,
      'total' => $total,
      'data' => $data,
    ];
    }

  private function decodeListParam(string $value): array
    {
    if (empty($value)) {
      return [];
      }
    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
      return $decoded;
      }
    return [$value];
    }

  private function buildWhereClauses(array $filters, string $username, array $listFilters): array
    {
    $where = [];
    $authColumn = $this->config['AUTH_COLUMN'];

    // Username access filter
    if (!empty($username)) {
      $safeUser = addslashes($username);
      $where[] = "{$authColumn} LIKE '%{$safeUser}%'";
      $where[] = "CONCAT(',', REPLACE(LOWER({$authColumn}), ' ', ''), ',') LIKE CONCAT('%,', LOWER('{$safeUser}'), ',%')";
      }

    // Standard filters from registry definitions
    foreach ($this->getFilterDefs() as $key => $def) {
      $value = $filters[$key] ?? '';
      if ($value === '' || $value === 'all')
        continue;

      $dbCol = $def[0];
      $type = $def[1];

      switch ($type) {
        case 'text_like':
          $safe = addslashes(strtolower($value));
          $where[] = "LOWER({$dbCol}) LIKE '%{$safe}%'";
          break;
        case 'equality':
          $safe = addslashes($value);
          $where[] = "{$dbCol} = '{$safe}'";
          break;
        case 'number_gte':
          $where[] = "{$dbCol} >= " . floatval($value);
          break;
        case 'number_lte':
          $where[] = "{$dbCol} <= " . floatval($value);
          break;
        case 'date_gte':
          $safe = addslashes($value);
          $where[] = "{$dbCol} >= '{$safe}'";
          break;
        case 'date_lte':
          $safe = addslashes($value);
          $where[] = "{$dbCol} <= '{$safe}'";
          break;
        case 'boolean_10':
          if (strtolower($value) === 'ja') {
            $where[] = "{$dbCol} = 1";
            } elseif (strtolower($value) === 'nein') {
            $where[] = "({$dbCol} = 0 OR {$dbCol} IS NULL)";
            }
          break;
        }
      }

    // Multi-select list filters from registry
    foreach ($this->getListFilterDefs() as $key => $def) {
      $list = $listFilters[$key] ?? [];
      $list = array_filter($list, function ($item) {
        return !empty($item) && strtolower($item) !== 'all';
        });
      if (empty($list))
        continue;

      $dbCol = $def[0];
      $castToInt = $def[1];

      if ($castToInt) {
        $values = array_map('intval', $list);
        $where[] = "{$dbCol} IN (" . implode(',', $values) . ")";
        } else {
        $values = array_map(function ($item) {
          return "'" . addslashes($item) . "'";
          }, $list);
        $where[] = "{$dbCol} IN (" . implode(',', $values) . ")";
        }
      }

    // Special filter: status
    $statusCol = $this->config['STATUS_COLUMN'];
    $escalationCol = $this->config['ESCALATION_COLUMN'];
    $dbHelper = $this->getDbHelper();

    if (!empty($filters['status']) && $filters['status'] !== 'all') {
      $statusValue = strtolower($filters['status']);
      $eskalationSql = $dbHelper->tryConvertDate($escalationCol);
      $currentDateSql = $dbHelper->currentDateOnly();

      switch ($statusValue) {
        case 'beendet':
        case 'completed':
          $where[] = "{$statusCol} = 'completed'";
          break;
        case 'aktiv_alle':
        case 'aktiv alle':
          $where[] = "{$statusCol} = 'rest'";
          break;
        case 'fällig':
        case 'faellig':
        case 'aktiv fällig':
        case 'aktiv faellig':
          $where[] = "({$statusCol} = 'rest' AND {$eskalationSql} <= {$currentDateSql})";
          break;
        case 'nicht fällig':
        case 'nicht faellig':
        case 'not_faellig':
        case 'aktiv nicht fällig':
        case 'aktiv nicht faellig':
          $where[] = "({$statusCol} = 'rest' AND ({$eskalationSql} > {$currentDateSql} OR {$escalationCol} IS NULL))";
          break;
        default:
          $value = addslashes($filters['status']);
          $where[] = "{$statusCol} = '{$value}'";
        }
      }

    // Special filter: laufzeit (DATEDIFF-based ranges)
    $laufzeitCol = $this->config['LAUFZEIT_COLUMN'];
    if (!empty($filters['laufzeit']) && $filters['laufzeit'] !== 'all') {
      $value = $filters['laufzeit'];
      $daysSql = $dbHelper->dateDiffDays($laufzeitCol);

      $matched = false;
      foreach ($this->config['LAUFZEIT_RANGES'] as $rangeLabel => $range) {
        if ($value === $rangeLabel) {
          $min = $range[0];
          $max = $range[1];
          if ($max === null) {
            $where[] = "({$daysSql} >= {$min})";
            } else {
            $where[] = "({$daysSql} >= {$min} AND {$daysSql} <= {$max})";
            }
          $matched = true;
          break;
          }
        }

      if (!$matched) {
        if (preg_match('/^(\d+)-(\d+)\s*Tage$/i', $value, $matches)) {
          $min = (int) $matches[1];
          $max = (int) $matches[2];
          $where[] = "({$daysSql} >= {$min} AND {$daysSql} <= {$max})";
          } elseif (preg_match('/^(\d+)\+\s*Tage$/i', $value, $matches)) {
          $min = (int) $matches[1];
          $where[] = "({$daysSql} >= {$min})";
          }
        }
      }

    // Special filter: coor (boolean flag mapping)
    $coorCol = $this->config['COOR_COLUMN'];
    if (!empty($filters['coor']) && $filters['coor'] !== 'all') {
      $value = strtolower($filters['coor']);
      if ($value === 'ja') {
        $where[] = "{$coorCol} = 1";
        } elseif ($value === 'nein') {
        $where[] = "{$coorCol} = 0";
        }
      }

    return $where;
    }

  /**
   * @param array $booleanFields  field-id => db-column for fields that map 0/1 → Nein/Ja
   */
  private function mapRow(array $row, string $username, array $booleanFields): array
    {
    $row = array_change_key_case($row, CASE_LOWER);

    // Standard field mapping from config
    $mapped = [];
    foreach ($this->getFieldMap() as $frontendKey => $dbCol) {
      $mapped[$frontendKey] = $row[$dbCol] ?? '';
      }

    // Auto boolean display: 1 → 'Ja', 0/null → 'Nein' for every boolean_10 field
    foreach ($booleanFields as $fieldId => $dbCol) {
      $raw = $row[strtolower($dbCol)] ?? null;
      $mapped[$fieldId] = ((int) $raw === 1) ? 'Ja' : 'Nein';
      }

    // Computed field: status label
    $processId = $row['processid'] ?? '';
    $statusCol = strtolower($this->config['STATUS_COLUMN']);
    $escalationCol = strtolower($this->config['ESCALATION_COLUMN']);
    $statusId = $row[$statusCol] ?? '';
    $statusLabels = $this->config['STATUS_LABELS'];
    $statusLabel = '';

    if ($statusId === 'completed') {
      $statusLabel = $statusLabels['completed'] ?? 'Beendet';
      } else if ($statusId === 'rest') {
      $eskalationDate = $row[$escalationCol] ?? '';
      if (!empty($eskalationDate)) {
        $eskalation = strtotime($eskalationDate);
        $today = strtotime('today');
        if ($eskalation <= $today) {
          $statusLabel = $statusLabels['due'] ?? 'Faellig';
          } else {
          $statusLabel = $statusLabels['not_due'] ?? 'Nicht Faellig';
          }
        } else {
        $statusLabel = $statusLabels['not_due'] ?? 'Nicht Faellig';
        }
      } else {
      $statusLabel = $statusId;
      }

    // Emit processid + jrkey so ROW_ACTIONS urlTemplate placeholders resolve.
    // historyLink is kept as a convenience (tooltip / backward-compat).
    $jrKey = $this->computeJrKey($processId, $username);
    $mapped['processid'] = $processId;
    $mapped['key'] = $jrKey;
    $mapped['historyLink'] = $this->buildTrackingLink($processId, $username, $jrKey);
    $mapped['status'] = $statusLabel;
    $mapped['invoice'] = $row['dokumentid'] ?? '';
    $mapped['protocol'] = $row['dokumentid'] ?? '';

    // Other (non-boolean) computed fields from config
    foreach ($this->config['COMPUTED_FIELDS'] as $fieldId => $def) {
      $source = strtolower($def['source']);
      $rawValue = $row[$source] ?? null;
      $mapping = $def['mapping'];
      if ($rawValue !== null && isset($mapping[(int) $rawValue])) {
        $mapped[$fieldId] = $mapping[(int) $rawValue];
        } elseif (isset($mapping[null])) {
        $mapped[$fieldId] = $mapping[null];
        }
      }

    return $mapped;
    }

  private function computeJrKey(string $processId, string $username): string
    {
    if ($processId === '' || $username === '')
      return '';
    $passphrase = $this->config['TRACKING_PASSPHRASE'];
    return md5($processId . $passphrase . strtolower($username));
    }

  private function buildTrackingLink(string $processId, string $username, string $jrKey): string
    {
    if ($processId === '' || $username === '' || $jrKey === '')
      return '';
    $baseUrl = $this->config['BASE_URL'];
    return $baseUrl . '/index.php'
      . '?cmd=Tracking_ShowTracking'
      . '&jrprocessid=' . urlencode($processId)
      . '&display=popup'
      . '&jrkey=' . $jrKey;
    }
  }

Query::execute();
