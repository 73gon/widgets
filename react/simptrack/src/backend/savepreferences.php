<?php

namespace dashboard\MyWidgets\Simptrack;

use JobRouter\Api\Dashboard\v1\Widget;
use Exception;
use Throwable;

require_once(__DIR__ . '/../../../includes/central.php');
require_once(__DIR__ . '/DatabaseHelper.php');
require_once(__DIR__ . '/ConfigNormalizer.php');
require_once(__DIR__ . '/Logger.php');

class SavePreferences extends Widget
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
        return 'Simptrack Save Preferences';
        }

    public static function execute(): void
        {
        try {
            $widget = new static();
            $response = $widget->handleRequest();
            header('Content-Type: application/json');
            echo json_encode($response);
            } catch (Exception $e) {
            \Logger::error('savepreferences.exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            http_response_code(500);
            echo json_encode(['error' => 'Exception: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            } catch (Throwable $e) {
            \Logger::error('savepreferences.throwable', [
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

    private function getParam(string $key, $default = '')
        {
        return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
        }

    private function handleRequest(): array
        {
        $t0 = microtime(true);
        $debug = !empty($this->config['DEBUG_LOG']);
        $JobDB = $this->getJobDB();
        $dbHelper = $this->getDbHelper();
        $tableName = $this->config['PREFERENCES_TABLE'];

        $dbHelper->ensurePreferencesTableSchema($tableName);

        $username = $this->getParam('username', '');
        $filter = $this->getParam('filter', null);
        $columnOrder = $this->getParam('column_order', null);
        $sortColumn = $this->getParam('sort_column', null);
        $sortDirection = $this->getParam('sort_direction', null);
        $currentPage = max(1, (int) $this->getParam('current_page', 1));
        $entriesPerPage = max(1, (int) $this->getParam('entries_per_page', 25));
        $zoomLevel = (float) $this->getParam('zoom_level', 1.0);
        $visibleColumns = $this->getParam('visible_columns', null);
        $visibleFilters = $this->getParam('visible_filters', null);
        $filterPresets = $this->getParam('filter_presets', null);
        $themeMode = $this->getParam('theme_mode', null);

        $quotedUsername = $dbHelper->quote($username);
        $safeFilter = $dbHelper->quoteOrNull($filter ?: null);
        $safeColumnOrder = $dbHelper->quoteOrNull($columnOrder ?: null);
        $safeSortColumn = $dbHelper->quoteOrNull($sortColumn ?: null);
        $safeSortDirection = $dbHelper->quoteOrNull($sortDirection ?: null);
        $safeVisibleColumns = $dbHelper->quoteOrNull($visibleColumns ?: null);
        $safeVisibleFilters = $dbHelper->quoteOrNull($visibleFilters ?: null);
        $safeFilterPresets = $dbHelper->quoteOrNull($filterPresets ?: null);
        $safeThemeMode = $dbHelper->quoteOrNull($themeMode ?: null);

        $checkQuery = "SELECT id FROM {$tableName} WHERE username = {$quotedUsername}";
        if ($debug) {
            $tc = microtime(true);
            $result = $JobDB->query($checkQuery);
            \Logger::debug('savepreferences.check', ['sql' => $checkQuery, 'ms' => \Logger::timeMs($tc)]);
            } else {
            $result = $JobDB->query($checkQuery);
            }
        $exists = $JobDB->fetchRow($result);

        $updatedAt = $dbHelper->updatedAtExpression();

        if ($exists) {
            $updateQuery = "
                UPDATE {$tableName} SET
                    filter = {$safeFilter},
                    column_order = {$safeColumnOrder},
                    sort_column = {$safeSortColumn},
                    sort_direction = {$safeSortDirection},
                    current_page = {$currentPage},
                    entries_per_page = {$entriesPerPage},
                    zoom_level = {$zoomLevel},
                    visible_columns = {$safeVisibleColumns},
                    visible_filters = {$safeVisibleFilters},
                    filter_presets = {$safeFilterPresets},
                    theme_mode = {$safeThemeMode},
                    updated_at = {$updatedAt}
                WHERE username = {$quotedUsername}
            ";
            if ($debug) {
                $tu = microtime(true);
                $JobDB->exec($updateQuery);
                \Logger::debug('savepreferences.update', ['ms' => \Logger::timeMs($tu)]);
                } else {
                $JobDB->exec($updateQuery);
                }
            } else {
            $insertQuery = "
                INSERT INTO {$tableName}
                (username, filter, column_order, sort_column, sort_direction, current_page, entries_per_page, zoom_level, visible_columns, visible_filters, filter_presets, theme_mode)
                VALUES (
                    {$quotedUsername},
                    {$safeFilter},
                    {$safeColumnOrder},
                    {$safeSortColumn},
                    {$safeSortDirection},
                    {$currentPage},
                    {$entriesPerPage},
                    {$zoomLevel},
                    {$safeVisibleColumns},
                    {$safeVisibleFilters},
                    {$safeFilterPresets},
                    {$safeThemeMode}
                )
            ";
            if ($debug) {
                $ti = microtime(true);
                $JobDB->exec($insertQuery);
                \Logger::debug('savepreferences.insert', ['ms' => \Logger::timeMs($ti)]);
                } else {
                $JobDB->exec($insertQuery);
                }
            }

        if ($debug) {
            \Logger::debug('savepreferences.result', ['user' => $username, 'action' => $exists ? 'update' : 'insert', 'total_ms' => \Logger::timeMs($t0)]);
            }

        return [
            'success' => true,
            'message' => 'Preferences saved successfully',
        ];
        }
    }

SavePreferences::execute();
