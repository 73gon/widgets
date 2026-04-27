import type { FilterPreset } from './types';

// ============================================
// DEFAULT FILTER PRESETS (UI-level convenience)
// ============================================
//
// Ships empty by design. Customers create their own presets at runtime via the
// preset-save UI; we deliberately do NOT bundle customer-specific presets
// (e.g. "Coor Schnittstelle") in the generic widget.

export const DEFAULT_FILTER_PRESETS: FilterPreset[] = [];

// ============================================
// LAZY LOADING CONFIGURATION
// ============================================

/** localStorage key for tracking the last time the user interacted with the widget */
export const INTERACTION_STORAGE_KEY = 'simplifyTable_lastInteraction';

/** Auto-load data without a click if user interacted within this many ms (3 hours) */
export const AUTO_LOAD_THRESHOLD_MS = 3 * 60 * 60 * 1000;
