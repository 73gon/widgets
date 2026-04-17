import type { Column, DropdownOption, FilterConfig, Filters, ServerColumn, ServerStandaloneFilter } from './types';

// ============================================
// SCHEMA DERIVATION
// ============================================
// Derives FilterConfig[], default filter values, and URL serialization directly
// from the init.php payload. config.php is THE single source of truth —
// the frontend is a thin consumer.

export interface ServerSchema {
  columns: ServerColumn[];
  standaloneFilters: ServerStandaloneFilter[];
  dropdownOptions: Record<string, DropdownOption[]>;
}

/** Plain Column[] for DEFAULT_COLUMNS-equivalent usage. */
export function columnsFromServer(serverColumns: ServerColumn[]): Column[] {
  return serverColumns.map((c) => ({
    id: c.id,
    label: c.label,
    type: c.type,
    align: c.align,
    visible: true,
  }));
}

/** Build default filter values from the server schema. */
export function defaultFiltersFromServer(schema: ServerSchema): Filters {
  const filters: Filters = {};

  for (const col of schema.columns) {
    const f = col.filter;
    if (!f) continue;
    if (f.rangeIds) {
      filters[f.rangeIds.fromId] = '';
      filters[f.rangeIds.toId] = '';
    } else {
      filters[f.key] = f.defaultValue ?? (f.type === 'autocomplete' ? [] : '');
    }
  }

  for (const f of schema.standaloneFilters) {
    filters[f.key] = f.defaultValue ?? (f.type === 'autocomplete' ? [] : '');
  }

  return filters;
}

/** Build FilterConfig[] (UI filter rendering config) from the server schema. */
export function filterConfigsFromServer(schema: ServerSchema): FilterConfig[] {
  const out: FilterConfig[] = [];

  for (const col of schema.columns) {
    const f = col.filter;
    if (!f) continue;
    out.push(buildFilterConfig(f.id ?? f.key, f.label ?? col.label, f, schema.dropdownOptions));
  }

  for (const f of schema.standaloneFilters) {
    out.push(buildFilterConfig(f.id ?? f.key, f.label ?? '', f, schema.dropdownOptions));
  }

  return out;
}

function buildFilterConfig(
  id: string,
  label: string,
  f: { type: FilterConfig['type']; key: string; optionsKey?: string; rangeIds?: { fromId: string; toId: string } },
  dropdowns: Record<string, DropdownOption[]>,
): FilterConfig {
  const config: FilterConfig = {
    id,
    label,
    type: f.type,
    filterKey: f.key,
    visible: true,
  };
  if (f.type === 'dropdown' || f.type === 'autocomplete') {
    config.options = dropdowns[f.optionsKey ?? f.key] ?? [];
  } else if (f.rangeIds) {
    config.options = f.rangeIds;
  }
  return config;
}

/** Serialize all filter values into URL search params for query.php. */
export function serializeFiltersToParams(url: URL, schema: ServerSchema, filters: Filters): void {
  const apply = (key: string, val: unknown) => {
    if (Array.isArray(val)) {
      if (val.length > 0) url.searchParams.set(key, JSON.stringify(val));
    } else if (typeof val === 'string' && val) {
      url.searchParams.set(key, val);
    }
  };

  for (const col of schema.columns) {
    const f = col.filter;
    if (!f) continue;
    if (f.rangeIds) {
      apply(f.rangeIds.fromId, filters[f.rangeIds.fromId]);
      apply(f.rangeIds.toId, filters[f.rangeIds.toId]);
    } else {
      apply(f.key, filters[f.key]);
    }
  }

  for (const f of schema.standaloneFilters) {
    apply(f.key, filters[f.key]);
  }
}
