<?php

namespace dashboard\MyWidgets\Simptrack;

use JobRouter\Api\Dashboard\v1\Widget;
use Exception;
use Throwable;

require_once(__DIR__ . '/../../../includes/central.php');
require_once(__DIR__ . '/DatabaseHelper.php');
require_once(__DIR__ . '/ConfigNormalizer.php');
require_once(__DIR__ . '/ConfigValidator.php');
require_once(__DIR__ . '/Logger.php');

class Init extends Widget
    {
    private array $config;
    private \DatabaseHelper $dbHelper;

    public function __construct()
        {
        $this->config = \ConfigNormalizer::normalize(require __DIR__ . '/config.php');
        \Logger::configure($this->config['LOG_DIR'] ?? __DIR__ . '/logs');
        }

    public function getTitle(): string
        {
        return 'Simptrack Init';
        }

    public static function execute(): void
        {
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
            \Logger::error('init.exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            http_response_code(500);
            echo json_encode(['error' => 'Exception: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            } catch (Throwable $e) {
            \Logger::error('init.throwable', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            http_response_code(500);
            echo json_encode(['error' => 'Error: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            }
        }

    private function getDbHelper(): \DatabaseHelper
        {
        if (!isset($this->dbHelper)) {
            $this->dbHelper = new \DatabaseHelper($this->getJobDB(), $this->config['DB_TYPE']);
            }
        return $this->dbHelper;
        }

    private function handleRequest(): array
        {
        $t0 = microtime(true);
        $debug = !empty($this->config['DEBUG_LOG']);

        (new \ConfigValidator($this->config, $this->getJobDB()))->validate();

        $username = isset($_GET['username']) ? trim($_GET['username']) : '';

        // Build row actions for frontend (strip server-only fields, resolve BASE_URL)
        $rowActions = [];
        foreach ($this->config['ROW_ACTIONS'] as $action) {
            if (!$action['enabled'])
                continue;
            $rowActions[] = [
                'id' => $action['id'],
                'label' => $action['label'],
                'icon' => $action['icon'],
                'urlTemplate' => str_replace('{BASE_URL}', $this->config['BASE_URL'], $action['urlTemplate']),
                'target' => $action['target'] ?? '_blank',
                'popupSize' => $action['popupSize'] ?? null,
                'condition' => $action['condition'] ?? null,
            ];
            }

        if ($debug) {
            \Logger::debug('init.request', ['user' => $username, 'data_view' => $this->config['DATA_VIEW']]);
            }

        $columns = $this->getColumns();
        $standaloneFilters = $this->getStandaloneFilters();
        $dropdownOptions = $this->getDropdownOptions();
        $spvFilter = $this->getSpvFilterMeta();
        $userPreferences = $this->loadUserPreferences($username);

        if ($debug) {
            \Logger::debug('init.result', [
                'columns' => count($columns),
                'dropdown_keys' => array_keys($dropdownOptions),
                'has_preferences' => $userPreferences !== null,
                'total_ms' => \Logger::timeMs($t0),
            ]);
            }

        return [
            'columns' => $columns,
            'standaloneFilters' => $standaloneFilters,
            'dropdownOptions' => $dropdownOptions,
            'spvFilter' => $spvFilter,
            'userPreferences' => $userPreferences,
            'rowActions' => $rowActions,
            'theme' => $this->config['THEME'],
            'locale' => $this->config['LOCALE'],
        ];
        }

    /**
     * Derive column definitions from config FIELD_MAP.
     * Each column carries its (optional) inline filter metadata so the frontend
     * can derive FilterConfig / defaults without duplicating the schema.
     */
    private function getColumns(): array
        {
        $columns = [];
        foreach ($this->config['FIELD_MAP'] as $field) {
            $col = [
                'id' => $field['id'],
                'label' => $field['label'],
                'type' => $field['type'],
                'align' => $field['align'],
            ];
            if (!empty($field['filter'])) {
                $col['filter'] = $this->normalizeFilterForFrontend($field['filter'], $field['label']);
                }
            $columns[] = $col;
            }
        return $columns;
        }

    /**
     * Filters with complex SQL handlers that don't correspond to a column.
     */
    private function getStandaloneFilters(): array
        {
        $out = [];
        foreach (($this->config['SPECIAL_FILTERS'] ?? []) as $filter) {
            $out[] = [
                'id' => $filter['id'],
                'label' => $filter['label'],
                'type' => $filter['type'],
                'key' => $filter['key'],
                'defaultValue' => $filter['defaultValue'] ?? ($filter['type'] === 'autocomplete' ? [] : ''),
                'optionsKey' => $filter['optionsKey'] ?? $filter['key'],
            ];
            }
        return $out;
        }

    /** Strip server-only fields from a FIELD_MAP filter before shipping to the frontend. */
    private function normalizeFilterForFrontend(array $filter, string $fieldLabel): array
        {
        $out = [
            'id' => $filter['id'] ?? $filter['key'],
            'label' => $filter['label'] ?? $fieldLabel,
            'type' => $filter['type'],
            'key' => $filter['key'],
            'defaultValue' => $filter['defaultValue'] ?? ($filter['type'] === 'autocomplete' ? [] : ''),
        ];
        if (!empty($filter['optionsKey']))
            $out['optionsKey'] = $filter['optionsKey'];
        if (!empty($filter['rangeIds']))
            $out['rangeIds'] = ['fromId' => $filter['rangeIds'][0], 'toId' => $filter['rangeIds'][1]];
        if (!empty($filter['listFilter']))
            $out['multi'] = true;
        return $out;
        }

    /**
     * Get dropdown options with file-based caching.
     */
    private function getDropdownOptions(): array
        {
        $debug = !empty($this->config['DEBUG_LOG']);
        $cacheDir = __DIR__ . '/cache';
        $cacheFile = $cacheDir . '/dropdown_options.json';
        $cacheTtl = $this->config['CACHE_TTL'];
        $cacheSignature = $this->buildDropdownOptionsCacheSignature();

        $cachedOptions = $this->readDropdownOptionsCache($cacheFile, $cacheSignature, $cacheTtl, $debug);
        if ($cachedOptions !== null) {
            return $cachedOptions;
            }

        if ($debug) {
            \Logger::debug('init.dropdown.cache', ['hit' => false, 'sources' => count($this->config['DROPDOWN_SOURCES'])]);
            }

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
            }

        $lockHandle = @fopen($cacheFile . '.lock', 'c');
        if ($lockHandle) {
            $lockStarted = microtime(true);
            if (@flock($lockHandle, LOCK_EX)) {
                if ($debug) {
                    \Logger::debug('init.dropdown.cache_lock', ['wait_ms' => \Logger::timeMs($lockStarted)]);
                    }

                $cachedOptions = $this->readDropdownOptionsCache($cacheFile, $cacheSignature, $cacheTtl, $debug);
                if ($cachedOptions !== null) {
                    @flock($lockHandle, LOCK_UN);
                    @fclose($lockHandle);
                    return $cachedOptions;
                    }

                $options = $this->fetchDropdownOptionsFromDB();
                $this->writeDropdownOptionsCache($cacheFile, $cacheSignature, $options);

                @flock($lockHandle, LOCK_UN);
                @fclose($lockHandle);
                return $options;
                }
            @fclose($lockHandle);
            }

        $options = $this->fetchDropdownOptionsFromDB();
        $this->writeDropdownOptionsCache($cacheFile, $cacheSignature, $options);

        return $options;
        }

    private function readDropdownOptionsCache(string $cacheFile, string $cacheSignature, int $cacheTtl, bool $debug): ?array
        {
        if (!file_exists($cacheFile)) {
            return null;
            }

        $age = time() - filemtime($cacheFile);
        if ($age >= $cacheTtl) {
            return null;
            }

        $cached = json_decode(file_get_contents($cacheFile), true);
        if (
            is_array($cached)
            && ($cached['signature'] ?? null) === $cacheSignature
            && isset($cached['options'])
            && is_array($cached['options'])
        ) {
            if ($debug) {
                \Logger::debug('init.dropdown.cache', ['hit' => true, 'age_s' => $age, 'ttl_s' => $cacheTtl]);
                }
            return $cached['options'];
            }

        return null;
        }

    private function writeDropdownOptionsCache(string $cacheFile, string $cacheSignature, array $options): void
        {
        @file_put_contents($cacheFile, json_encode([
            'signature' => $cacheSignature,
            'options' => $options,
        ]));
        }

    private function buildDropdownOptionsCacheSignature(): string
        {
        return md5(json_encode([
            'dataView' => $this->config['DATA_VIEW'] ?? null,
            'dropdownSources' => $this->config['DROPDOWN_SOURCES'] ?? [],
            'staticDropdowns' => $this->config['STATIC_DROPDOWNS'] ?? [],
            'spvFilter' => $this->config['SPV_FILTER'] ?? null,
        ]));
        }

    /** Frontend metadata for the SPV badge in the matching autocomplete filter. */
    private function getSpvFilterMeta(): ?array
        {
        $spv = $this->config['SPV_FILTER'] ?? null;
        if (empty($spv) || empty($spv['optionsKey']))
            return null;
        return [
            'optionsKey' => $spv['optionsKey'],
            'label' => $spv['label'] ?? 'SPV',
            'color' => $spv['color'] ?? '#14b8a6',
        ];
        }

    /**
     * Query DB for dropdown options using config-defined sources,
     * then merge with static (hardcoded) dropdown options.
     */
    private function fetchDropdownOptionsFromDB(): array
        {
        $JobDB = $this->getJobDB();
        $dataView = $this->config['DATA_VIEW'];
        $options = [];

        $spv = $this->config['SPV_FILTER'] ?? null;
        $spvOptionsKey = $spv['optionsKey'] ?? null;
        $spvColumn = $spv['column'] ?? null;
        $spvValue = isset($spv['value']) ? (string) $spv['value'] : null;

        $groupedSources = [];
        $singleSources = [];
        foreach ($this->config['DROPDOWN_SOURCES'] as $key => $source) {
            $table = $source['table'] === 'DATA_VIEW' ? $dataView : $source['table'];
            if (!empty($source['distinct']) && empty($source['orderBy'])) {
                $source['_resolvedTable'] = $table;
                $groupedSources[$table][$key] = $source;
                } else {
                $source['_resolvedTable'] = $table;
                $singleSources[$key] = $source;
                }
            }

        foreach ($groupedSources as $table => $sources) {
            if (count($sources) > 1) {
                $this->fetchGroupedDropdownSources($JobDB, $table, $sources, $options, $spvOptionsKey, $spvColumn, $spvValue);
                } else {
                foreach ($sources as $key => $source) {
                    $singleSources[$key] = $source;
                    }
                }
            }

        foreach ($singleSources as $key => $source) {
            $table = $source['_resolvedTable'];
            $valueCol = $source['valueCol'];
            $labelCol = $source['labelCol'];
            $distinct = !empty($source['distinct']) ? 'DISTINCT' : '';
            $orderBy = !empty($source['orderBy']) ? "ORDER BY {$source['orderBy']}" : '';

            $isSpvSource = ($spvOptionsKey === $key && $spvColumn !== null);
            $selectCols = $valueCol . ', ' . $labelCol;
            if ($isSpvSource) {
                $selectCols .= ', ' . $spvColumn;
                }

            $query = "SELECT {$distinct} {$selectCols} FROM {$table} {$orderBy}";
            if (!empty($this->config['DEBUG_LOG'])) {
                $tq = microtime(true);
                $result = $JobDB->query($query);
                \Logger::debug('init.dropdown.query', ['key' => $key, 'sql' => $query, 'ms' => \Logger::timeMs($tq)]);
                } else {
                $result = $JobDB->query($query);
                }
            $items = [];
            while ($row = $JobDB->fetchRow($result)) {
                $value = $row[$valueCol] ?? ($row[strtolower($valueCol)] ?? null);
                $label = $row[$labelCol] ?? ($row[strtolower($labelCol)] ?? null);
                if ($this->isBlank($value) || $this->isBlank($label)) {
                    continue;
                    }
                $item = ['id' => $value, 'label' => $label];
                if ($isSpvSource) {
                    $rawMarker = $row[$spvColumn] ?? ($row[strtolower($spvColumn)] ?? null);
                    if ($rawMarker !== null && (string) $rawMarker === $spvValue) {
                        $item['marked'] = true;
                        }
                    }
                $items[] = $item;
                }
            $options[$key] = $items;
            }

        // Merge static dropdowns (these override/add to DB-queried ones)
        foreach ($this->config['STATIC_DROPDOWNS'] as $key => $items) {
            $options[$key] = $items;
            }

        return $options;
        }

    private function fetchGroupedDropdownSources($JobDB, string $table, array $sources, array &$options, ?string $spvOptionsKey, ?string $spvColumn, ?string $spvValue): void
        {
        $selectColumns = [];
        foreach ($sources as $source) {
            $selectColumns[$source['valueCol']] = true;
            $selectColumns[$source['labelCol']] = true;
            }
        if ($spvOptionsKey !== null && $spvColumn !== null && isset($sources[$spvOptionsKey])) {
            $selectColumns[$spvColumn] = true;
            }

        $query = 'SELECT ' . implode(', ', array_keys($selectColumns)) . " FROM {$table}";
        $started = microtime(true);
        $result = $JobDB->query($query);

        $itemsByKey = [];
        $seenByKey = [];
        foreach ($sources as $key => $source) {
            $itemsByKey[$key] = [];
            $seenByKey[$key] = [];
            }

        $rows = 0;
        while ($row = $JobDB->fetchRow($result)) {
            $rows++;
            foreach ($sources as $key => $source) {
                $value = $this->getRowValue($row, $source['valueCol']);
                $label = $this->getRowValue($row, $source['labelCol']);
                if ($this->isBlank($value) || $this->isBlank($label)) {
                    continue;
                    }

                $dedupeKey = (string) $value . "\x1f" . (string) $label;
                if (isset($seenByKey[$key][$dedupeKey])) {
                    continue;
                    }

                $item = ['id' => $value, 'label' => $label];
                if ($spvOptionsKey === $key && $spvColumn !== null) {
                    $rawMarker = $this->getRowValue($row, $spvColumn);
                    if ($rawMarker !== null && (string) $rawMarker === $spvValue) {
                        $item['marked'] = true;
                        }
                    }

                $seenByKey[$key][$dedupeKey] = true;
                $itemsByKey[$key][] = $item;
                }
            }

        foreach ($itemsByKey as $key => $items) {
            $options[$key] = $items;
            }

        if (!empty($this->config['DEBUG_LOG'])) {
            \Logger::debug('init.dropdown.group_query', [
                'keys' => array_keys($sources),
                'sql' => $query,
                'rows' => $rows,
                'option_counts' => array_map('count', $itemsByKey),
                'ms' => \Logger::timeMs($started),
            ]);
            }
        }

    private function isBlank($value): bool
        {
        return $value === null || trim((string) $value) === '';
        }

    private function getRowValue(array $row, string $column)
        {
        return $row[$column] ?? ($row[strtolower($column)] ?? ($row[strtoupper($column)] ?? null));
        }

    /**
     * Initialize the user preferences table if it doesn't exist.
     */
    private function initializeUserPreferencesTable(): void
        {
        $dbHelper = $this->getDbHelper();
        $tableName = $this->config['PREFERENCES_TABLE'];

        $dbHelper->ensurePreferencesTableSchema($tableName);
        }

    /**
     * Load user preferences from database.
     */
    private function loadUserPreferences(string $username): ?array
        {
        if ($username === '') {
            return null;
            }

        $this->initializeUserPreferencesTable();

        $JobDB = $this->getJobDB();
        $tableName = $this->config['PREFERENCES_TABLE'];
        $safeUsername = addslashes($username);

        $query = "SELECT * FROM {$tableName} WHERE username = '{$safeUsername}'";
        if (!empty($this->config['DEBUG_LOG'])) {
            $tp = microtime(true);
            $result = $JobDB->query($query);
            \Logger::debug('init.preferences.query', ['sql' => $query, 'ms' => \Logger::timeMs($tp)]);
            } else {
            $result = $JobDB->query($query);
            }
        $row = $JobDB->fetchRow($result);

        if ($row) {
            return [
                'filter' => $row['filter'] ? json_decode(stripslashes($row['filter']), true) : null,
                'column_order' => $row['column_order'] ? json_decode(stripslashes($row['column_order']), true) : null,
                'sort_column' => $row['sort_column'],
                'sort_direction' => $row['sort_direction'],
                'current_page' => (int) $row['current_page'],
                'entries_per_page' => (int) $row['entries_per_page'],
                'zoom_level' => isset($row['zoom_level']) ? (float) $row['zoom_level'] : 1.0,
                'visible_columns' => $row['visible_columns'] ? json_decode(stripslashes($row['visible_columns']), true) : null,
                'visible_filters' => $row['visible_filters'] ? json_decode(stripslashes($row['visible_filters']), true) : null,
                'filter_presets' => $row['filter_presets'] ? json_decode(stripslashes($row['filter_presets']), true) : null,
                'theme_mode' => $row['theme_mode'] ?? null,
            ];
            }

        return null;
        }
    }

Init::execute();
