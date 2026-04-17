<?php

namespace dashboard\MyWidgets\SimplifyTable;

use JobRouter\Api\Dashboard\v1\Widget;
use Exception;
use Throwable;

require_once(__DIR__ . '/../../../includes/central.php');
require_once(__DIR__ . '/DatabaseHelper.php');
require_once(__DIR__ . '/ConfigValidator.php');
require_once(__DIR__ . '/Logger.php');

class Init extends Widget
    {
    private array $config;
    private \DatabaseHelper $dbHelper;

    public function __construct()
        {
        $this->config = require __DIR__ . '/config.php';
        \Logger::configure($this->config['LOG_DIR'] ?? __DIR__ . '/logs');
        }

    public function getTitle(): string
        {
        return 'SimplifyTable Init';
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

        return [
            'columns' => $this->getColumns(),
            'standaloneFilters' => $this->getStandaloneFilters(),
            'dropdownOptions' => $this->getDropdownOptions(),
            'userPreferences' => $this->loadUserPreferences($username),
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
        $cacheDir = __DIR__ . '/cache';
        $cacheFile = $cacheDir . '/dropdown_options.json';
        $cacheTtl = $this->config['CACHE_TTL'];

        if (file_exists($cacheFile)) {
            $age = time() - filemtime($cacheFile);
            if ($age < $cacheTtl) {
                $cached = json_decode(file_get_contents($cacheFile), true);
                if (is_array($cached)) {
                    return $cached;
                    }
                }
            }

        $options = $this->fetchDropdownOptionsFromDB();

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
            }
        @file_put_contents($cacheFile, json_encode($options));

        return $options;
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

        // DB-queried dropdowns from config
        foreach ($this->config['DROPDOWN_SOURCES'] as $key => $source) {
            $table = $source['table'] === 'DATA_VIEW' ? $dataView : $source['table'];
            $valueCol = $source['valueCol'];
            $labelCol = $source['labelCol'];
            $distinct = !empty($source['distinct']) ? 'DISTINCT' : '';
            $orderBy = !empty($source['orderBy']) ? "ORDER BY {$source['orderBy']}" : '';

            $query = "SELECT {$distinct} {$valueCol}, {$labelCol} FROM {$table} {$orderBy}";
            $result = $JobDB->query($query);
            $items = [];
            while ($row = $JobDB->fetchRow($result)) {
                $items[] = ['id' => $row[$valueCol], 'label' => $row[$labelCol]];
                }
            $options[$key] = $items;
            }

        // Merge static dropdowns (these override/add to DB-queried ones)
        foreach ($this->config['STATIC_DROPDOWNS'] as $key => $items) {
            $options[$key] = $items;
            }

        return $options;
        }

    /**
     * Initialize the user preferences table if it doesn't exist.
     */
    private function initializeUserPreferencesTable(): void
        {
        $dbHelper = $this->getDbHelper();
        $tableName = $this->config['PREFERENCES_TABLE'];

        if (!$dbHelper->tableExists($tableName)) {
            $createQuery = $dbHelper->createPreferencesTableSQL($tableName);
            $this->getJobDB()->exec($createQuery);
            }
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
        $result = $JobDB->query($query);
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
