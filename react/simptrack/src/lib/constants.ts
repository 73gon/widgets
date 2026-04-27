import type { FilterPreset } from './types';

// ============================================
// DEFAULT FILTER PRESETS (UI-level convenience)
// ============================================

export const DEFAULT_FILTER_PRESETS: FilterPreset[] = [
  {
    id: 'overdue',
    label: 'Überfällige Rechnungen',
    filters: { status: 'faellig' },
    sort: { column: 'dueDate', direction: 'asc' },
    visible: true,
    isCustom: false,
  },
  {
    id: 'coor',
    label: 'Coor Schnittstelle',
    filters: { status: 'aktiv_alle', schritt: ['4001'] },
    visible: true,
    isCustom: false,
  },
];

// ============================================
// LAZY LOADING CONFIGURATION
// ============================================

/** localStorage key for tracking the last time the user interacted with the widget */
export const INTERACTION_STORAGE_KEY = 'simplifyTable_lastInteraction';

/** Auto-load data without a click if user interacted within this many ms (3 hours) */
export const AUTO_LOAD_THRESHOLD_MS = 3 * 60 * 60 * 1000;
