import { useMemo, useState, useCallback, useLayoutEffect, useRef } from 'react';
import { useSimplifyTable } from '@/lib/simplify-table-context';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { HugeiconsIcon } from '@hugeicons/react';
import {
  ArrowUp01Icon,
  ArrowDown01Icon,
  CheckmarkCircle02Icon,
  AlertCircleIcon,
  Clock01Icon,
  HelpCircleIcon,
  File01Icon,
  Invoice03Icon,
  ClipboardIcon,
  TimeQuarterIcon,
  Database01Icon,
} from '@hugeicons/core-free-icons';
import type { TableRow as TableRowType, Column, RowAction } from '@/lib/types';

export function DataTable() {
  const { state, columns, setSort, setColumnOrder, loadData } = useSimplifyTable();
  const [draggedColumn, setDraggedColumn] = useState<number | null>(null);
  const [dragOverColumn, setDragOverColumn] = useState<number | null>(null);
  const [rowHeight, setRowHeight] = useState<number | null>(null);
  const bodyContainerRef = useRef<HTMLDivElement | null>(null);
  const headerRowRef = useRef<HTMLTableRowElement | null>(null);

  // Get visible columns
  const visibleColumns = useMemo(() => columns.filter((col) => col.visible !== false), [columns]);

  // Data is already paginated server-side, use it directly
  const paginatedData = state.filteredData;

  useLayoutEffect(() => {
    const container = bodyContainerRef.current;
    if (!container) return;

    const resizeObserver = new ResizeObserver(() => {
      const containerHeight = container.clientHeight;
      const headerHeight = headerRowRef.current?.offsetHeight ?? 0;
      const availableHeight = Math.max(containerHeight - headerHeight, 0);
      const rowCount = Math.max(3, Math.min(paginatedData.length || 0, state.itemsPerPage));
      if (!availableHeight || !rowCount) {
        setRowHeight(null);
        return;
      }

      const preferred = 32;
      const min = 22;
      const computed = Math.floor(availableHeight / rowCount);
      const next = Math.max(min, Math.min(preferred, computed));
      setRowHeight(next);
    });

    resizeObserver.observe(container);
    return () => resizeObserver.disconnect();
  }, [paginatedData.length, state.itemsPerPage]);

  const handleSort = (columnId: string) => {
    if (columnId === 'actions') return;

    const newDirection = state.sortColumn === columnId && state.sortDirection === 'asc' ? 'desc' : 'asc';

    setSort(columnId, newDirection);
  };

  // Drag and drop handlers
  const handleDragStart = useCallback((e: React.DragEvent, index: number) => {
    if (index === 0) {
      e.preventDefault();
      return;
    }
    setDraggedColumn(index);
    e.dataTransfer.effectAllowed = 'move';
  }, []);

  const handleDragOver = useCallback(
    (e: React.DragEvent, index: number) => {
      e.preventDefault();
      if (index === 0 || draggedColumn === null) return;
      setDragOverColumn(index);
    },
    [draggedColumn],
  );

  const handleDragLeave = useCallback(() => {
    setDragOverColumn(null);
  }, []);

  const handleDrop = useCallback(
    (e: React.DragEvent, targetIndex: number) => {
      e.preventDefault();
      if (draggedColumn === null || targetIndex === 0 || draggedColumn === 0) return;

      const newOrder = [...state.columnOrder];
      const [moved] = newOrder.splice(draggedColumn, 1);
      newOrder.splice(targetIndex, 0, moved);
      setColumnOrder(newOrder);

      setDraggedColumn(null);
      setDragOverColumn(null);
    },
    [draggedColumn, state.columnOrder, setColumnOrder],
  );

  const handleDragEnd = useCallback(() => {
    setDraggedColumn(null);
    setDragOverColumn(null);
  }, []);

  if (!state.isDataLoaded && !state.loading) {
    return (
      <div className='flex flex-col items-center justify-center h-full text-muted-foreground rounded-xl border gap-4'>
        <HugeiconsIcon icon={Database01Icon} size={48} className='opacity-50' />
        <p className='text-lg'>Tabelle wurde noch nicht geladen</p>
        <Button onClick={loadData} variant='default' size='lg'>
          Tabelle laden
        </Button>
      </div>
    );
  }

  if (state.loading) {
    return <LoadingSkeleton columns={visibleColumns.length} />;
  }

  if (paginatedData.length === 0) {
    return (
      <div className='flex flex-col items-center justify-center h-full text-muted-foreground rounded-xl border'>
        <HugeiconsIcon icon={ClipboardIcon} size={48} className='mb-4 opacity-50' />
        <p className='text-lg'>Es wurden keine Einträge gefunden</p>
      </div>
    );
  }

  return (
    <div
      ref={bodyContainerRef}
      className='rounded-xl border overflow-auto flex flex-col h-full scrollbar-thumb-[#FFCC00] scrollbar-track-transparent'
      style={
        rowHeight
          ? ({
              '--table-row-height': `${rowHeight}px`,
            } as React.CSSProperties)
          : undefined
      }
    >
      <Table className='border-separate border-spacing-0 min-w-max'>
        <TableHeader className='bg-card sticky top-0 z-10'>
          <TableRow ref={headerRowRef} className='hover:bg-transparent'>
            {visibleColumns.map((column, index) => (
              <TableHead
                key={column.id}
                className={`
                  bg-card shadow-[0_1px_0_0_rgba(0,0,0,0.1)]
                  whitespace-nowrap select-none
                  ${column.id !== 'actions' ? 'cursor-pointer hover:bg-muted/50' : ''}
                  ${draggedColumn === index ? 'opacity-50' : ''}
                  ${dragOverColumn === index ? 'border-l-2 border-primary' : ''}
                `}
                style={{ textAlign: column.align }}
                draggable={column.id !== 'actions'}
                onDragStart={(e) => handleDragStart(e, index)}
                onDragOver={(e) => handleDragOver(e, index)}
                onDragLeave={handleDragLeave}
                onDrop={(e) => handleDrop(e, index)}
                onDragEnd={handleDragEnd}
                onClick={() => handleSort(column.id)}
              >
                <div className='flex items-center gap-1'>
                  <span>{column.label}</span>
                  {column.id !== 'actions' && <SortIndicator active={state.sortColumn === column.id} direction={state.sortDirection} />}
                </div>
              </TableHead>
            ))}
          </TableRow>
        </TableHeader>
        <TableBody>
          {paginatedData.map((row) => (
            <DataTableRow key={row.id} row={row} columns={visibleColumns} />
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

interface SortIndicatorProps {
  active: boolean;
  direction: 'asc' | 'desc';
}

function SortIndicator({ active, direction }: SortIndicatorProps) {
  return (
    <div className='flex flex-col -space-y-1'>
      <HugeiconsIcon icon={ArrowUp01Icon} size={12} className={active && direction === 'asc' ? 'text-primary' : 'text-muted-foreground/30'} />
      <HugeiconsIcon icon={ArrowDown01Icon} size={12} className={active && direction === 'desc' ? 'text-primary' : 'text-muted-foreground/30'} />
    </div>
  );
}

interface DataTableRowProps {
  row: TableRowType;
  columns: Column[];
}

function DataTableRow({ row, columns }: DataTableRowProps) {
  const status = (row.status || '').toLowerCase();
  const isDue = status === 'faellig' || status === 'fällig' || status === 'due';
  const isNotDue = status === 'not_faellig' || status === 'nicht fällig' || status === 'not_due';

  return (
    <TableRow
      className={`
        ${isDue ? 'bg-red-500/15 hover:bg-red-500/20' : ''}
        ${isNotDue ? 'bg-yellow-500/15 hover:bg-yellow-500/20' : ''}
      `}
    >
      {columns.map((column) => (
        <TableCell key={column.id} style={{ textAlign: column.align }} className='whitespace-nowrap'>
          <CellContent column={column} row={row} />
        </TableCell>
      ))}
    </TableRow>
  );
}

interface CellContentProps {
  column: Column;
  row: TableRowType;
}

function CellContent({ column, row }: CellContentProps) {
  const { state } = useSimplifyTable();
  const value = row[column.id];

  if (column.type === 'actions') {
    return <ActionsCell row={row} />;
  }

  if (column.type === 'status') {
    return <StatusCell status={row.status} runtime={row.runtime} dueDate={row.dueDate} />;
  }

  if (column.type === 'currency') {
    const numValue = typeof value === 'number' ? value : parseFloat((value as string) || '0');
    const formatted = numValue.toLocaleString(state.locale.language, {
      style: 'currency',
      currency: state.locale.currency,
      minimumFractionDigits: 2,
    });
    const colorClass = numValue > 0 ? 'text-green-600 font-medium' : numValue < 0 ? 'text-red-600 font-medium' : '';
    return <span className={colorClass}>{value ? formatted : '-'}</span>;
  }

  if (column.type === 'date') {
    if (!value) return <span>-</span>;
    const date = new Date(value as string);
    return (
      <span>
        {date.toLocaleDateString(state.locale.language, {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
        })}
      </span>
    );
  }

  return <span>{value ? String(value) : '-'}</span>;
}

interface StatusCellProps {
  status?: string;
  runtime?: string;
  dueDate?: string;
}

function StatusCell({ status, runtime, dueDate }: StatusCellProps) {
  const statusLower = (status || '').toLowerCase();

  let icon = HelpCircleIcon;
  let color = 'text-muted-foreground';
  let tooltip = 'Unbekannt';

  if (statusLower === 'beendet' || statusLower === 'completed') {
    icon = CheckmarkCircle02Icon;
    color = '!text-green-500';
    tooltip = runtime ? `Beendet - Laufzeit: ${runtime}` : 'Beendet';
  } else if (statusLower === 'fällig' || statusLower === 'faellig' || statusLower === 'due') {
    icon = AlertCircleIcon;
    color = '!text-red-500';

    if (dueDate) {
      const dueDateObj = new Date(dueDate);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      dueDateObj.setHours(0, 0, 0, 0);
      const diffTime = today.getTime() - dueDateObj.getTime();
      const daysOverdue = Math.floor(diffTime / (1000 * 60 * 60 * 24));
      tooltip = daysOverdue >= 0 ? `Fällig seit ${daysOverdue} Tagen` : 'Fällig';
    } else {
      tooltip = 'Fällig';
    }
  } else if (statusLower === 'nicht fällig' || statusLower === 'nicht faellig' || statusLower === 'not_due' || statusLower === 'not_faellig') {
    icon = Clock01Icon;
    color = '!text-yellow-500';
    tooltip = runtime ? `Nicht Fällig - Laufzeit: ${runtime}` : 'Nicht Fällig';
  }

  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger>
          <HugeiconsIcon icon={icon} size={19} className={color} />
        </TooltipTrigger>
        <TooltipContent>
          <p>{tooltip}</p>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}

interface ActionsCellProps {
  row: TableRowType;
}

const ACTION_ICON_MAP: Record<string, typeof TimeQuarterIcon> = {
  history: TimeQuarterIcon,
  invoice: Invoice03Icon,
  protocol: File01Icon,
};

const ACTION_COLOR_MAP: Record<string, { active: string; hover: string; bg: string }> = {
  history: { active: '!text-blue-500', hover: 'hover:!text-blue-600', bg: 'hover:bg-blue-500/10' },
  invoice: { active: 'text-amber-500!', hover: 'hover:text-amber-600!', bg: 'hover:bg-amber-500/10' },
  protocol: { active: 'text-purple-500!', hover: 'hover:text-purple-600!', bg: 'hover:bg-purple-500/10' },
};

function resolveUrlTemplate(template: string, row: TableRowType): string {
  return template.replace(/\{(\w+)\}/g, (_, key) => {
    const val = row[key];
    return val != null ? String(val) : '';
  });
}

function evaluateCondition(condition: string | undefined, row: TableRowType): boolean {
  if (!condition) return true;
  // Simple condition format: "field=value" or "status=beendet"
  const match = condition.match(/^(\w+)=(.+)$/);
  if (!match) return true;
  const [, field, expected] = match;
  const actual = String(row[field] ?? '').toLowerCase();
  return actual === expected.toLowerCase();
}

function ActionsCell({ row }: ActionsCellProps) {
  const { state } = useSimplifyTable();

  // If no row actions configured, fall back to nothing
  if (!state.rowActions || state.rowActions.length === 0) {
    return null;
  }

  return (
    <div className='flex items-center justify-center gap-0'>
      {state.rowActions.map((action) => (
        <ActionButton key={action.id} action={action} row={row} />
      ))}
    </div>
  );
}

interface ActionButtonProps {
  action: RowAction;
  row: TableRowType;
}

function ActionButton({ action, row }: ActionButtonProps) {
  const icon = ACTION_ICON_MAP[action.id] ?? File01Icon;
  const colors = ACTION_COLOR_MAP[action.id] ?? ACTION_COLOR_MAP.history;

  // Determine the data key for this action (e.g., historyLink, invoice, protocol)
  const dataKey = action.id === 'history' ? 'historyLink' : action.id;
  const hasData = !!row[dataKey];
  const conditionMet = evaluateCondition(action.condition, row);
  const isEnabled = hasData && conditionMet;

  const resolvedUrl = isEnabled ? resolveUrlTemplate(action.urlTemplate, row) : '';

  const handleClick = (e: React.MouseEvent) => {
    if (!isEnabled || !resolvedUrl) return;
    e.preventDefault();
    if (action.target === 'popup') {
      const w = action.popupSize?.width ?? 1000;
      const h = action.popupSize?.height ?? 700;
      window.open(resolvedUrl, '_blank', `width=${w},height=${h},resizable=yes,scrollbars=yes`);
    } else {
      window.open(resolvedUrl, action.target);
    }
  };

  const tooltipText = isEnabled
    ? `${action.label}: ${row[dataKey] ?? ''}`
    : !conditionMet
      ? `${action.label} nicht verfügbar (Bedingung nicht erfüllt)`
      : `Kein(e) ${action.label} verfügbar`;

  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger
          onClick={handleClick}
          className={`p-1 rounded transition-colors ${isEnabled ? `${colors.active} ${colors.hover} ${colors.bg} cursor-pointer` : 'text-muted-foreground/30 cursor-not-allowed'}`}
          disabled={!isEnabled}
        >
          <HugeiconsIcon icon={icon} size={19} />
        </TooltipTrigger>
        <TooltipContent>
          <p>{tooltipText}</p>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}

interface LoadingSkeletonProps {
  columns: number;
}

function LoadingSkeleton({ columns }: LoadingSkeletonProps) {
  return (
    <div className='rounded-xl border overflow-hidden h-full'>
      <Table>
        <TableHeader>
          <TableRow>
            {Array.from({ length: columns }).map((_, i) => (
              <TableHead key={i}>
                <Skeleton className='h-4 w-20' />
              </TableHead>
            ))}
          </TableRow>
        </TableHeader>
        <TableBody>
          {Array.from({ length: 10 }).map((_, rowIndex) => (
            <TableRow key={rowIndex}>
              {Array.from({ length: columns }).map((_, colIndex) => (
                <TableCell key={colIndex}>
                  <Skeleton className='h-4' style={{ width: `${Math.random() * 40 + 40}%` }} />
                </TableCell>
              ))}
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}
