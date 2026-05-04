import { createContext, useContext, useState, useCallback, useMemo, useEffect, useRef, type ReactNode } from 'react';
import type {
  AppState,
  Filters,
  FilterPreset,
  Column,
  FilterConfig,
  RowAction,
  LocaleConfig,
  ThemeConfig,
  ServerColumn,
  ServerStandaloneFilter,
  AppError,
} from './types';
import { DEFAULT_FILTER_PRESETS, INTERACTION_STORAGE_KEY, AUTO_LOAD_THRESHOLD_MS } from './constants';
import { columnsFromServer, defaultFiltersFromServer, filterConfigsFromServer, serializeFiltersToParams, type ServerSchema } from './schema';

interface SimplifyTableContextType {
  state: AppState;
  columns: Column[];
  filterConfigs: FilterConfig[];

  // Actions
  setFilters: (filters: Partial<Filters>) => void;
  resetFilters: () => void;
  setSort: (column: string | null, direction: 'asc' | 'desc') => void;
  setPage: (page: number) => void;
  setItemsPerPage: (count: number) => void;
  setColumnOrder: (order: string[]) => void;
  toggleColumnVisibility: (columnId: string) => void;
  toggleFilterVisibility: (filterId: string) => void;
  setFilterPresets: (presets: FilterPreset[]) => void;
  addFilterPreset: (preset: FilterPreset) => void;
  removeFilterPreset: (presetId: string) => void;
  togglePresetVisibility: (presetId: string) => void;
  reorderPresets: (fromIndex: number, toIndex: number) => void;
  applyPreset: (presetId: string) => void;
  setSelectedPreset: (presetId: string | null) => void;
  setZoom: (level: number) => void;
  toggleTheme: () => void;
  dismissError: () => void;
  fetchData: (overrides?: {
    filters?: Filters;
    currentPage?: number;
    itemsPerPage?: number;
    sortColumn?: string | null;
    sortDirection?: 'asc' | 'desc';
  }) => void;
  fetchAllForExport: () => Promise<import('./types').TableRow[]>;
  loadData: () => void;
}

const SimplifyTableContext = createContext<SimplifyTableContextType | null>(null);

export function useSimplifyTable() {
  const context = useContext(SimplifyTableContext);
  if (!context) {
    throw new Error('useSimplifyTable must be used within a SimplifyTableProvider');
  }
  return context;
}

const EMPTY_SCHEMA: ServerSchema = { columns: [], standaloneFilters: [], dropdownOptions: {} };

async function parseResponse(response: Response): Promise<unknown> {
  const text = await response.text();
  try {
    return JSON.parse(text);
  } catch {
    return { error: text || `Request failed: ${response.status}` };
  }
}

function errorFromPayload(payload: unknown): AppError {
  if (payload && typeof payload === 'object' && 'error' in payload) {
    const p = payload as { error: unknown; rule?: unknown; hint?: unknown; details?: unknown };
    return {
      message: String(p.error ?? 'Unknown error'),
      rule: typeof p.rule === 'string' ? p.rule : undefined,
      hint: typeof p.hint === 'string' ? p.hint : null,
      details: p.details && typeof p.details === 'object' ? (p.details as Record<string, unknown>) : undefined,
    };
  }
  return { message: 'Unknown error' };
}

interface SimplifyTableProviderProps {
  children: ReactNode;
}

export function SimplifyTableProvider({ children }: SimplifyTableProviderProps) {
  const [state, setState] = useState<AppState>(() => ({
    data: [],
    filteredData: [],
    filters: {},
    currentPage: 1,
    itemsPerPage: 25,
    totalItems: 0,
    sortColumn: null,
    sortDirection: 'asc',
    loading: false,
    isDataLoaded: false,
    columnOrder: [],
    visibleColumns: [],
    visibleFilters: [],
    filterPresets: DEFAULT_FILTER_PRESETS,
    selectedPreset: null,
    zoomLevel: 1.0,
    themeMode: 'dark',
    theme: { primaryColor: '#ffcc00', defaultMode: 'dark' },
    rowActions: [],
    locale: { language: 'de-DE', currency: 'EUR' },
    error: null,
  }));

  const [schema, setSchema] = useState<ServerSchema>(EMPTY_SCHEMA);
  const [columnsConfig, setColumnsConfig] = useState<Column[]>([]);
  const currentUserRef = useRef('');
  const [isInitialized, setIsInitialized] = useState(false);
  const stateRef = useRef(state);
  const schemaRef = useRef(schema);

  useEffect(() => {
    stateRef.current = state;
  }, [state]);
  useEffect(() => {
    schemaRef.current = schema;
  }, [schema]);

  const columns = useMemo(() => {
    return state.columnOrder
      .map((id) => columnsConfig.find((c) => c.id === id))
      .filter((c): c is Column => c !== undefined)
      .map((c) => ({ ...c, visible: state.visibleColumns.includes(c.id) }));
  }, [state.columnOrder, state.visibleColumns, columnsConfig]);

  const filterConfigs = useMemo(() => {
    return filterConfigsFromServer(schema).map((f) => ({
      ...f,
      visible: state.visibleFilters.includes(f.id),
    }));
  }, [state.visibleFilters, schema]);

  const buildEndpoint = useCallback((fileName: string) => {
    return new URL(fileName, window.location.href).toString();
  }, []);

  const fetchJson = useCallback(async (url: string, options?: RequestInit) => {
    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
    });
    const payload = await parseResponse(response);
    if (!response.ok) {
      const err = errorFromPayload(payload);
      throw Object.assign(new Error(err.message), { appError: err });
    }
    return payload;
  }, []);

  const setError = useCallback((err: AppError | null) => {
    setState((prev) => ({ ...prev, error: err }));
  }, []);

  const dismissError = useCallback(() => setError(null), [setError]);

  type QuerySnapshot = Pick<AppState, 'currentPage' | 'itemsPerPage' | 'sortColumn' | 'sortDirection' | 'filters'>;

  const queryServer = useCallback(
    async (snapshot: QuerySnapshot) => {
      const url = new URL(buildEndpoint('query.php'));

      url.searchParams.set('page', String(snapshot.currentPage));
      url.searchParams.set('perPage', String(snapshot.itemsPerPage));
      url.searchParams.set('sortColumn', snapshot.sortColumn ?? '');
      url.searchParams.set('sortDirection', snapshot.sortDirection);
      url.searchParams.set('username', currentUserRef.current);

      serializeFiltersToParams(url, schemaRef.current, snapshot.filters);

      return fetchJson(url.toString());
    },
    [buildEndpoint, fetchJson],
  );

  const touchInteractionTimestamp = useCallback(() => {
    try {
      localStorage.setItem(INTERACTION_STORAGE_KEY, String(Date.now()));
    } catch {
      // localStorage may not be available (e.g. private browsing quota)
    }
  }, []);

  const shouldAutoLoad = useCallback((): boolean => {
    try {
      const stored = localStorage.getItem(INTERACTION_STORAGE_KEY);
      if (!stored) return false;
      const elapsed = Date.now() - Number(stored);
      return elapsed < AUTO_LOAD_THRESHOLD_MS;
    } catch {
      return false;
    }
  }, []);

  const fetchData = useCallback(
    async (overrides?: Partial<QuerySnapshot>) => {
      const snapshot: QuerySnapshot = {
        currentPage: overrides?.currentPage ?? stateRef.current.currentPage,
        itemsPerPage: overrides?.itemsPerPage ?? stateRef.current.itemsPerPage,
        sortColumn: overrides?.sortColumn ?? stateRef.current.sortColumn,
        sortDirection: overrides?.sortDirection ?? stateRef.current.sortDirection,
        filters: overrides?.filters ?? stateRef.current.filters,
      };

      setState((prev) => ({ ...prev, loading: true }));

      try {
        const response = (await queryServer(snapshot)) as { data?: unknown; total?: number };
        const data = Array.isArray(response?.data) ? (response.data as import('./types').TableRow[]) : [];
        const total = typeof response?.total === 'number' ? response.total : data.length;

        setState((prev) => ({
          ...prev,
          data,
          filteredData: data,
          totalItems: total,
          loading: false,
          isDataLoaded: true,
        }));

        touchInteractionTimestamp();
      } catch (error) {
        const appErr = (error as { appError?: AppError }).appError ?? { message: String((error as Error).message ?? 'Request failed') };
        setState((prev) => ({ ...prev, loading: false, error: appErr }));
      }
    },
    [queryServer, touchInteractionTimestamp],
  );

  useEffect(() => {
    let isCancelled = false;

    const initialize = async () => {
      setState((prev) => ({ ...prev, loading: true }));

      try {
        const url = new URL(window.location.href);
        const username = url.searchParams.get('username') ?? '';
        currentUserRef.current = username;

        const initUrl = new URL(buildEndpoint('init.php'));
        if (username) {
          initUrl.searchParams.set('username', username);
        }

        const initResponse = (await fetchJson(initUrl.toString())) as {
          columns?: ServerColumn[];
          standaloneFilters?: ServerStandaloneFilter[];
          dropdownOptions?: Record<string, import('./types').DropdownOption[]>;
          spvFilter?: import('./types').SpvFilterMeta | null;
          userPreferences?: import('./types').UserPreferences | null;
          rowActions?: RowAction[];
          theme?: Partial<ThemeConfig>;
          locale?: LocaleConfig;
        };

        if (isCancelled) return;

        const serverColumns = Array.isArray(initResponse.columns) ? initResponse.columns : [];
        const serverStandalone = Array.isArray(initResponse.standaloneFilters) ? initResponse.standaloneFilters : [];
        const serverDropdowns = initResponse.dropdownOptions ?? {};
        const serverSchema: ServerSchema = {
          columns: serverColumns,
          standaloneFilters: serverStandalone,
          dropdownOptions: serverDropdowns,
          spvFilter: initResponse.spvFilter ?? null,
        };

        const preferences = initResponse.userPreferences ?? null;
        const serverRowActions: RowAction[] = Array.isArray(initResponse.rowActions) ? initResponse.rowActions : [];
        const serverLocale: LocaleConfig = initResponse.locale ?? { language: 'de-DE', currency: 'EUR' };
        const serverTheme: ThemeConfig = {
          primaryColor: initResponse.theme?.primaryColor || '#ffcc00',
          defaultMode: initResponse.theme?.defaultMode === 'light' ? 'light' : 'dark',
        };
        const serverDefaultTheme: 'light' | 'dark' = initResponse.theme?.defaultMode === 'light' ? 'light' : 'dark';

        const initColumns = columnsFromServer(serverColumns);
        const defaultFilters = defaultFiltersFromServer(serverSchema);
        const defaultColumnOrder = initColumns.map((c) => c.id);
        const defaultVisibleFilters = filterConfigsFromServer(serverSchema).map((f) => f.id);

        setSchema(serverSchema);
        setColumnsConfig(initColumns);

        const mergeNewItems = (saved: string[], serverDefined: string[]): string[] => {
          const savedSet = new Set(saved);
          const newItems = serverDefined.filter((id) => !savedSet.has(id));
          return [...saved, ...newItems];
        };

        const nextFilters = preferences?.filter ? { ...defaultFilters, ...preferences.filter } : defaultFilters;
        const nextSortColumn = preferences?.sort_column ?? stateRef.current.sortColumn;
        const nextSortDirection = preferences?.sort_direction ?? stateRef.current.sortDirection;
        const nextCurrentPage = preferences?.current_page ?? stateRef.current.currentPage;
        const nextItemsPerPage = preferences?.entries_per_page ?? stateRef.current.itemsPerPage;

        setState((prev) => ({
          ...prev,
          filters: nextFilters,
          sortColumn: nextSortColumn,
          sortDirection: nextSortDirection,
          currentPage: nextCurrentPage,
          itemsPerPage: nextItemsPerPage,
          zoomLevel: preferences?.zoom_level ?? prev.zoomLevel,
          columnOrder: preferences?.column_order?.length ? mergeNewItems(preferences.column_order, defaultColumnOrder) : defaultColumnOrder,
          visibleColumns: preferences?.visible_columns?.length ? mergeNewItems(preferences.visible_columns, defaultColumnOrder) : defaultColumnOrder,
          visibleFilters: preferences?.visible_filters?.length
            ? mergeNewItems(preferences.visible_filters, defaultVisibleFilters)
            : defaultVisibleFilters,
          filterPresets: preferences?.filter_presets?.length ? preferences.filter_presets : prev.filterPresets,
          themeMode: preferences?.theme_mode ?? serverDefaultTheme,
          theme: serverTheme,
          rowActions: serverRowActions,
          locale: serverLocale,
          error: null,
        }));

        if (shouldAutoLoad()) {
          await fetchData({
            filters: nextFilters,
            sortColumn: nextSortColumn,
            sortDirection: nextSortDirection,
            currentPage: nextCurrentPage,
            itemsPerPage: nextItemsPerPage,
          });
        } else {
          setState((prev) => ({ ...prev, loading: false }));
        }
      } catch (error) {
        const appErr = (error as { appError?: AppError }).appError ?? { message: String((error as Error).message ?? 'Init failed') };
        setState((prev) => ({ ...prev, loading: false, error: appErr }));
      } finally {
        if (!isCancelled) {
          setIsInitialized(true);
        }
      }
    };

    void initialize();

    return () => {
      isCancelled = true;
    };
  }, [buildEndpoint, fetchJson, fetchData, shouldAutoLoad]);

  const savePreferences = useCallback(async () => {
    if (!currentUserRef.current) return;

    const payload = new URLSearchParams();
    payload.set('username', currentUserRef.current);
    payload.set('filter', JSON.stringify(stateRef.current.filters));
    payload.set('column_order', JSON.stringify(stateRef.current.columnOrder));
    payload.set('sort_column', stateRef.current.sortColumn ?? '');
    payload.set('sort_direction', stateRef.current.sortDirection);
    payload.set('current_page', String(stateRef.current.currentPage));
    payload.set('entries_per_page', String(stateRef.current.itemsPerPage));
    payload.set('zoom_level', String(stateRef.current.zoomLevel));
    payload.set('visible_columns', JSON.stringify(stateRef.current.visibleColumns));
    payload.set('visible_filters', JSON.stringify(stateRef.current.visibleFilters));
    payload.set('filter_presets', JSON.stringify(stateRef.current.filterPresets));
    payload.set('theme_mode', stateRef.current.themeMode);

    try {
      await fetch(buildEndpoint('savepreferences.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload.toString(),
        credentials: 'same-origin',
      });
    } catch {
      // ignore save errors for now
    }
  }, [buildEndpoint]);

  useEffect(() => {
    if (!isInitialized) return;

    const timer = window.setTimeout(() => {
      void savePreferences();
    }, 600);

    return () => window.clearTimeout(timer);
  }, [
    isInitialized,
    savePreferences,
    state.filters,
    state.columnOrder,
    state.sortColumn,
    state.sortDirection,
    state.currentPage,
    state.itemsPerPage,
    state.zoomLevel,
    state.visibleColumns,
    state.visibleFilters,
    state.filterPresets,
    state.themeMode,
  ]);

  const setFilters = useCallback((newFilters: Partial<Filters>) => {
    setState((prev) => ({
      ...prev,
      filters: { ...prev.filters, ...newFilters } as Filters,
      currentPage: 1,
      selectedPreset: 'individual',
    }));
  }, []);

  const resetFilters = useCallback(() => {
    const defaults = defaultFiltersFromServer(schemaRef.current);
    setState((prev) => ({
      ...prev,
      filters: defaults,
      currentPage: 1,
      selectedPreset: null,
    }));
    void fetchData({ filters: defaults, currentPage: 1 });
  }, [fetchData]);

  const loadData = useCallback(() => {
    void fetchData();
  }, [fetchData]);

  const setSort = useCallback(
    (column: string | null, direction: 'asc' | 'desc') => {
      setState((prev) => ({ ...prev, sortColumn: column, sortDirection: direction, currentPage: 1 }));
      void fetchData({ sortColumn: column, sortDirection: direction, currentPage: 1 });
    },
    [fetchData],
  );

  const setPage = useCallback(
    (page: number) => {
      setState((prev) => ({ ...prev, currentPage: page }));
      void fetchData({ currentPage: page });
    },
    [fetchData],
  );

  const setItemsPerPage = useCallback(
    (count: number) => {
      setState((prev) => ({ ...prev, itemsPerPage: count, currentPage: 1 }));
      void fetchData({ itemsPerPage: count, currentPage: 1 });
    },
    [fetchData],
  );

  const setColumnOrder = useCallback((order: string[]) => {
    setState((prev) => ({ ...prev, columnOrder: order }));
  }, []);

  const toggleColumnVisibility = useCallback((columnId: string) => {
    if (columnId === 'actions') return;
    setState((prev) => {
      const isVisible = prev.visibleColumns.includes(columnId);
      const visibleColumns = isVisible ? prev.visibleColumns.filter((id) => id !== columnId) : [...prev.visibleColumns, columnId];
      return { ...prev, visibleColumns };
    });
  }, []);

  const toggleFilterVisibility = useCallback((filterId: string) => {
    setState((prev) => {
      const isVisible = prev.visibleFilters.includes(filterId);
      const visibleFilters = isVisible ? prev.visibleFilters.filter((id) => id !== filterId) : [...prev.visibleFilters, filterId];
      return { ...prev, visibleFilters };
    });
  }, []);

  const setFilterPresets = useCallback((presets: FilterPreset[]) => {
    setState((prev) => ({ ...prev, filterPresets: presets }));
  }, []);

  const addFilterPreset = useCallback((preset: FilterPreset) => {
    setState((prev) => ({ ...prev, filterPresets: [...prev.filterPresets, preset], selectedPreset: preset.id }));
  }, []);

  const removeFilterPreset = useCallback((presetId: string) => {
    setState((prev) => ({
      ...prev,
      filterPresets: prev.filterPresets.filter((p) => p.id !== presetId),
      selectedPreset: prev.selectedPreset === presetId ? null : prev.selectedPreset,
    }));
  }, []);

  const togglePresetVisibility = useCallback((presetId: string) => {
    setState((prev) => ({
      ...prev,
      filterPresets: prev.filterPresets.map((p) => (p.id === presetId ? { ...p, visible: !p.visible } : p)),
    }));
  }, []);

  const reorderPresets = useCallback((fromIndex: number, toIndex: number) => {
    setState((prev) => {
      const newPresets = [...prev.filterPresets];
      const [moved] = newPresets.splice(fromIndex, 1);
      newPresets.splice(toIndex, 0, moved);
      return { ...prev, filterPresets: newPresets };
    });
  }, []);

  const applyPreset = useCallback(
    (presetId: string) => {
      setState((prev) => {
        const preset = prev.filterPresets.find((p) => p.id === presetId);
        if (!preset) return prev;

        const defaults = defaultFiltersFromServer(schemaRef.current);
        const newFilters = { ...defaults, ...preset.filters } as Filters;
        const sortColumn = preset.sort?.column ?? prev.sortColumn;
        const sortDirection = preset.sort?.direction ?? prev.sortDirection;

        void fetchData({ filters: newFilters, sortColumn, sortDirection, currentPage: 1 });

        return { ...prev, filters: newFilters, sortColumn, sortDirection, currentPage: 1, selectedPreset: presetId };
      });
    },
    [fetchData],
  );

  const setSelectedPreset = useCallback((presetId: string | null) => {
    setState((prev) => ({ ...prev, selectedPreset: presetId }));
  }, []);

  const setZoom = useCallback((level: number) => {
    const clampedLevel = Math.max(0.5, Math.min(2.0, level));
    setState((prev) => ({ ...prev, zoomLevel: clampedLevel }));
  }, []);

  const toggleTheme = useCallback(() => {
    setState((prev) => ({ ...prev, themeMode: prev.themeMode === 'dark' ? 'light' : 'dark' }));
  }, []);

  const fetchAllForExport = useCallback(async () => {
    const snapshot: QuerySnapshot = {
      currentPage: 1,
      itemsPerPage: stateRef.current.itemsPerPage,
      sortColumn: stateRef.current.sortColumn,
      sortDirection: stateRef.current.sortDirection,
      filters: stateRef.current.filters,
    };

    const url = new URL(buildEndpoint('query.php'));
    url.searchParams.set('page', '1');
    url.searchParams.set('perPage', '1');
    url.searchParams.set('export', '1');
    url.searchParams.set('sortColumn', snapshot.sortColumn ?? '');
    url.searchParams.set('sortDirection', snapshot.sortDirection);
    url.searchParams.set('username', currentUserRef.current);
    serializeFiltersToParams(url, schemaRef.current, snapshot.filters);

    try {
      const response = (await fetchJson(url.toString())) as { data?: unknown };
      return Array.isArray(response?.data) ? (response.data as import('./types').TableRow[]) : [];
    } catch (err) {
      const appErr = (err as { appError?: AppError }).appError ?? {
        message: String((err as Error).message ?? 'Export fehlgeschlagen'),
      };
      setError(appErr);
      throw err;
    }
  }, [buildEndpoint, fetchJson, setError]);

  const value = useMemo(
    () => ({
      state,
      columns,
      filterConfigs,
      setFilters,
      resetFilters,
      setSort,
      setPage,
      setItemsPerPage,
      setColumnOrder,
      toggleColumnVisibility,
      toggleFilterVisibility,
      setFilterPresets,
      addFilterPreset,
      removeFilterPreset,
      togglePresetVisibility,
      reorderPresets,
      applyPreset,
      setSelectedPreset,
      setZoom,
      toggleTheme,
      dismissError,
      fetchData,
      fetchAllForExport,
      loadData,
    }),
    [
      state,
      columns,
      filterConfigs,
      setFilters,
      resetFilters,
      setSort,
      setPage,
      setItemsPerPage,
      setColumnOrder,
      toggleColumnVisibility,
      toggleFilterVisibility,
      setFilterPresets,
      addFilterPreset,
      removeFilterPreset,
      togglePresetVisibility,
      reorderPresets,
      applyPreset,
      setSelectedPreset,
      setZoom,
      toggleTheme,
      dismissError,
      fetchData,
      fetchAllForExport,
      loadData,
    ],
  );

  return <SimplifyTableContext.Provider value={value}>{children}</SimplifyTableContext.Provider>;
}
