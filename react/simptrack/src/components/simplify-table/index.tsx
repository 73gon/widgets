import { useEffect } from 'react';
import { SimplifyTableProvider, useSimplifyTable } from '@/lib/simplify-table-context';
import { FilterBar } from './filter-bar';
import { DataTable } from './data-table';
import { Pagination } from './pagination';
import { TableHeader } from './table-header';
import { ErrorBanner } from './error-banner';

function SimplifyTableContent() {
  const { state } = useSimplifyTable();

  return (
    <div
      className={`p-4 h-full max-h-full flex flex-col bg-background overflow-hidden`}
      style={{
        zoom: state.zoomLevel,
      }}
    >
      <TableHeader />
      <ErrorBanner />
      <FilterBar />
      <div className='flex-1 min-h-0 overflow-hidden'>
        <DataTable />
      </div>
      {state.isDataLoaded && <Pagination />}
    </div>
  );
}

export function SimplifyTable() {
  return (
    <SimplifyTableProvider>
      <SimplifyTableRoot />
    </SimplifyTableProvider>
  );
}

function SimplifyTableRoot() {
  const { state } = useSimplifyTable();

  useEffect(() => {
    const widget = document.querySelector<HTMLElement>('.simptrack-widget');
    if (!widget) return;
    widget.classList.remove('light', 'dark');
    widget.classList.add(state.themeMode);

    const primaryColor = state.theme.primaryColor;
    const primaryForeground = getReadableForeground(primaryColor);
    widget.style.setProperty('--primary', primaryColor);
    widget.style.setProperty('--primary-foreground', primaryForeground);
    widget.style.setProperty('--ring', primaryColor);
    widget.style.setProperty('--chart-1', primaryColor);
    widget.style.setProperty('--chart-3', primaryColor);
    widget.style.setProperty('--sidebar-primary', primaryColor);
    widget.style.setProperty('--sidebar-primary-foreground', primaryForeground);
    widget.style.setProperty('--sidebar-ring', primaryColor);
  }, [state.themeMode, state.theme.primaryColor]);

  return (
    <div className='w-full h-full max-h-full overflow-hidden'>
      <SimplifyTableContent />
    </div>
  );
}

function getReadableForeground(hexColor: string): string {
  const hex = hexColor.replace('#', '').trim();
  const normalized =
    hex.length === 3
      ? hex
          .split('')
          .map((char) => char + char)
          .join('')
      : hex;

  if (!/^[0-9a-fA-F]{6}$/.test(normalized)) {
    return '#222222';
  }

  const red = parseInt(normalized.slice(0, 2), 16);
  const green = parseInt(normalized.slice(2, 4), 16);
  const blue = parseInt(normalized.slice(4, 6), 16);
  const luminance = (0.299 * red + 0.587 * green + 0.114 * blue) / 255;

  return luminance > 0.55 ? '#222222' : '#ffffff';
}

export { FilterBar } from './filter-bar';
export { DataTable } from './data-table';
export { Pagination } from './pagination';
export { TableHeader } from './table-header';
export { SettingsModal } from './settings-modal';
