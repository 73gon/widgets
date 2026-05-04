// ============================================
// TYPE DEFINITIONS
// ============================================

export interface Column {
  id: string;
  label: string;
  type: 'actions' | 'status' | 'date' | 'text' | 'currency' | 'number';
  align: 'left' | 'center' | 'right';
  visible?: boolean;
}

export interface DropdownOption {
  id: string;
  label: string;
  /** Optional marker flag set by the backend (e.g. SPV companies). */
  marked?: boolean;
}

/** Metadata for a marker badge attached to an autocomplete filter (e.g. SPV). */
export interface SpvFilterMeta {
  optionsKey: string;
  label: string;
  color: string;
}

/** Extensible map of dropdown option lists, keyed by filter/options name */
export type DropdownOptions = Record<string, DropdownOption[]>;

/** Extensible filter state — keys and defaults are defined in field-registry.ts */
export type Filters = Record<string, string | string[]>;

export interface FilterPreset {
  id: string;
  label: string;
  filters: Partial<Filters>;
  sort?: {
    column: string;
    direction: 'asc' | 'desc';
  };
  visible?: boolean;
  isCustom?: boolean;
}

export interface FilterConfig {
  id: string;
  label: string;
  type: 'dropdown' | 'text' | 'autocomplete' | 'daterange' | 'numberrange';
  filterKey: string;
  options?: DropdownOption[] | { fromId: string; toId: string };
  visible?: boolean;
  /** Marker badge metadata when this autocomplete has a flagged subset (e.g. SPV). */
  marker?: { label: string; color: string };
}

export interface AppError {
  message: string;
  rule?: string;
  hint?: string | null;
  details?: Record<string, unknown>;
}

export interface TableRow {
  id: string;
  status: string;
  incident?: string;
  entryDate?: string;
  stepLabel?: string;
  startDate?: string;
  jobFunction?: string;
  fullName?: string;
  documentId?: string;
  companyName?: string;
  fund?: string;
  creditorName?: string;
  invoiceType?: string;
  invoiceNumber?: string;
  invoiceDate?: string;
  grossAmount?: number | string;
  dueDate?: string;
  orderId?: string;
  paymentAmount?: number | string;
  paymentDate?: string;
  chargeable?: string;
  runtime?: string;
  historyLink?: string;
  /** Raw JobRouter process id — used by ROW_ACTIONS urlTemplate {processid}. */
  processid?: string;
  /** MD5 tracking key — used by ROW_ACTIONS urlTemplate {key}. */
  key?: string;
  invoice?: string;
  protocol?: string;
  [key: string]: unknown;
}

// ============================================
// SERVER SCHEMA (init.php payload)
// ============================================

export type FilterType = 'dropdown' | 'text' | 'autocomplete' | 'daterange' | 'numberrange';

export interface ServerFilter {
  id?: string;
  label?: string;
  type: FilterType;
  key: string;
  defaultValue?: string | string[];
  optionsKey?: string;
  rangeIds?: { fromId: string; toId: string };
  multi?: boolean;
}

export interface ServerColumn {
  id: string;
  label: string;
  type: Column['type'];
  align: Column['align'];
  filter?: ServerFilter;
}

export interface ServerStandaloneFilter {
  id: string;
  label: string;
  type: FilterType;
  key: string;
  defaultValue?: string | string[];
  optionsKey?: string;
}

export interface ServerError {
  error: string;
  rule?: string;
  hint?: string | null;
  details?: Record<string, unknown>;
  file?: string;
  line?: number;
}

export interface RowAction {
  id: string;
  label: string;
  icon: string;
  urlTemplate: string;
  target: '_blank' | '_self' | 'popup';
  popupSize?: { width: number; height: number };
  condition?: string;
}

export interface LocaleConfig {
  language: string;
  currency: string;
}

export interface ThemeConfig {
  primaryColor: string;
  defaultMode: 'light' | 'dark';
}

export interface UserPreferences {
  filter?: Filters;
  column_order?: string[];
  sort_column?: string;
  sort_direction?: 'asc' | 'desc';
  current_page?: number;
  entries_per_page?: number;
  zoom_level?: number;
  visible_columns?: string[];
  visible_filters?: string[];
  filter_presets?: FilterPreset[];
  theme_mode?: 'light' | 'dark';
}

export interface AppState {
  data: TableRow[];
  filteredData: TableRow[];
  filters: Filters;
  currentPage: number;
  itemsPerPage: number;
  totalItems: number;
  sortColumn: string | null;
  sortDirection: 'asc' | 'desc';
  loading: boolean;
  isDataLoaded: boolean;
  columnOrder: string[];
  visibleColumns: string[];
  visibleFilters: string[];
  filterPresets: FilterPreset[];
  selectedPreset: string | null;
  zoomLevel: number;
  themeMode: 'light' | 'dark';
  theme: ThemeConfig;
  rowActions: RowAction[];
  locale: LocaleConfig;
  error: AppError | null;
}
