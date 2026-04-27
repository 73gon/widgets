import { useState, useRef } from 'react';
import { useSimplifyTable } from '@/lib/simplify-table-context';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { DatePicker } from '@/components/ui/date-picker';
import { HugeiconsIcon } from '@hugeicons/react';
import { FilterIcon, FilterRemoveIcon, Tick01Icon, Cancel01Icon, FloppyDiskIcon, ArrowUp01Icon, ArrowDown01Icon } from '@hugeicons/core-free-icons';
import type { FilterConfig, DropdownOption, Filters } from '@/lib/types';

export function FilterBar() {
  const { state, filterConfigs, resetFilters, applyPreset, addFilterPreset, fetchData } = useSimplifyTable();

  const [savingPreset, setSavingPreset] = useState(false);
  const [newPresetName, setNewPresetName] = useState('');
  const [isCollapsed, setIsCollapsed] = useState(false);
  const presetNameInputRef = useRef<HTMLInputElement>(null);

  // Get visible presets for the select
  const visiblePresets = state.filterPresets.filter((p) => p.visible !== false);

  const handlePresetChange = (value: string | null) => {
    if (!value || value === 'individual') return;
    applyPreset(value);
  };

  const handleSavePreset = () => {
    if (!newPresetName.trim()) return;

    const newPresetId = `custom-${Date.now()}`;
    const newPreset = {
      id: newPresetId,
      label: newPresetName.trim(),
      filters: { ...state.filters },
      sort: state.sortColumn ? { column: state.sortColumn, direction: state.sortDirection } : undefined,
      visible: true,
      isCustom: true,
    };

    addFilterPreset(newPreset);
    applyPreset(newPresetId); // Auto-select the new preset
    setNewPresetName('');
    setSavingPreset(false);
  };

  const handleCancelSave = () => {
    setNewPresetName('');
    setSavingPreset(false);
  };

  const handleApplyFilters = () => {
    fetchData({ filters: state.filters });
  };

  return (
    <div className='bg-card rounded-xl border p-4 mb-4 shrink-0'>
      {/* Header with collapse toggle */}
      <div className={`flex items-center justify-between ${isCollapsed ? '' : 'mb-4'}`}>
        <span className='text-sm font-medium text-muted-foreground'>Filter</span>
        <Button variant='ghost' size='icon' className='h-6 w-6' onClick={() => setIsCollapsed(!isCollapsed)}>
          <HugeiconsIcon icon={isCollapsed ? ArrowDown01Icon : ArrowUp01Icon} size={14} />
        </Button>
      </div>

      {!isCollapsed && (
        <>
          {/* Filter Grid */}
          <div className='grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-4'>
            {filterConfigs
              .filter((f) => f.visible)
              .map((config) => (
                <FilterItem key={config.id} config={config} />
              ))}
          </div>

          {/* Actions Row */}
          <div className='flex flex-wrap items-center justify-between gap-4 pt-2 border-t'>
            {/* Preset Select and Save */}
            <div className='flex items-center gap-2'>
              <Select value={state.selectedPreset || 'individual'} onValueChange={handlePresetChange}>
                <SelectTrigger className='w-50'>
                  <SelectValue>
                    {state.selectedPreset === 'individual' || !state.selectedPreset
                      ? 'Individuell'
                      : visiblePresets.find((p) => p.id === state.selectedPreset)?.label || 'Filter auswählen...'}
                  </SelectValue>
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value='individual'>Individuell</SelectItem>
                  {visiblePresets.map((preset) => (
                    <SelectItem key={preset.id} value={preset.id}>
                      {preset.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              {!savingPreset ? (
                <Button variant='outline' onClick={() => setSavingPreset(true)} className='gap-1 h-7.25'>
                  <HugeiconsIcon icon={FloppyDiskIcon} size={16} />
                  Filter speichern
                </Button>
              ) : (
                <div className='relative flex items-center'>
                  <Input
                    ref={presetNameInputRef}
                    value={newPresetName}
                    onChange={(e) => setNewPresetName(e.target.value)}
                    placeholder='Filtername...'
                    className='w-38.75 h-7 pr-14'
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') handleSavePreset();
                      if (e.key === 'Escape') handleCancelSave();
                    }}
                    autoFocus
                  />
                  <div className='absolute right-1 flex items-center gap-0.5'>
                    <button
                      type='button'
                      className='p-1 rounded text-green-500 hover:text-green-600 hover:bg-green-500/10 disabled:opacity-50 disabled:cursor-not-allowed'
                      onClick={handleSavePreset}
                      disabled={!newPresetName.trim()}
                    >
                      <HugeiconsIcon icon={Tick01Icon} size={16} />
                    </button>
                    <button type='button' className='p-1 rounded text-red-500 hover:text-red-600 hover:bg-red-500/10' onClick={handleCancelSave}>
                      <HugeiconsIcon icon={Cancel01Icon} size={16} />
                    </button>
                  </div>
                </div>
              )}
            </div>

            {/* Apply and Reset Buttons */}
            <div className='flex items-center gap-2'>
              <Button onClick={handleApplyFilters} className='gap-1'>
                <HugeiconsIcon icon={FilterIcon} size={16} />
                Filter anwenden
              </Button>
              <Button variant='destructive' onClick={resetFilters} className='gap-1'>
                <HugeiconsIcon icon={FilterRemoveIcon} size={16} />
                Zurücksetzen
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}

interface FilterItemProps {
  config: FilterConfig;
}

function FilterItem({ config }: FilterItemProps) {
  const { state, setFilters } = useSimplifyTable();

  if (config.type === 'dropdown') {
    const options = config.options as DropdownOption[];
    const value = state.filters[config.filterKey as keyof Filters] as string;
    const getLabel = (id: string) => {
      if (id === 'all') return 'Alle';
      return options.find((opt) => opt.id === id)?.label || id;
    };

    return (
      <div className='space-y-1.5'>
        <label className='text-xs font-medium text-muted-foreground uppercase tracking-wide'>{config.label}</label>
        <Select value={value || 'all'} onValueChange={(val) => setFilters({ [config.filterKey]: val || 'all' })}>
          <SelectTrigger className='w-full'>
            <SelectValue>{getLabel(value || 'all')}</SelectValue>
          </SelectTrigger>
          <SelectContent>
            <SelectItem value='all'>Alle</SelectItem>
            {options.map((opt) => (
              <SelectItem key={opt.id} value={opt.id}>
                {opt.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
    );
  }

  if (config.type === 'autocomplete') {
    return <AutocompleteFilter config={config} />;
  }

  if (config.type === 'daterange') {
    const rangeOptions = config.options as { fromId: string; toId: string };
    const fromValue = state.filters[rangeOptions.fromId as keyof Filters] as string;
    const toValue = state.filters[rangeOptions.toId as keyof Filters] as string;

    return (
      <div className='space-y-3'>
        <label className='text-xs font-medium text-muted-foreground uppercase tracking-wide'>{config.label}</label>
        <div className='grid grid-cols-2 gap-2'>
          <DatePicker value={fromValue} onChange={(date) => setFilters({ [rangeOptions.fromId]: date })} placeholder='Von' />
          <DatePicker value={toValue} onChange={(date) => setFilters({ [rangeOptions.toId]: date })} placeholder='Bis' />
        </div>
      </div>
    );
  }

  if (config.type === 'numberrange') {
    const rangeOptions = config.options as { fromId: string; toId: string };
    const fromValue = state.filters[rangeOptions.fromId as keyof Filters] as string;
    const toValue = state.filters[rangeOptions.toId as keyof Filters] as string;

    return (
      <div className='space-y-1.5'>
        <label className='text-xs font-medium text-muted-foreground uppercase tracking-wide'>{config.label}</label>
        <div className='flex items-center gap-2'>
          <Input
            type='number'
            step='0.01'
            placeholder='Von'
            value={fromValue || ''}
            onChange={(e) => setFilters({ [rangeOptions.fromId]: e.target.value })}
            className='flex-1 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none'
          />
          <span className='text-muted-foreground'>-</span>
          <Input
            type='number'
            step='0.01'
            placeholder='Bis'
            value={toValue || ''}
            onChange={(e) => setFilters({ [rangeOptions.toId]: e.target.value })}
            className='flex-1 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none'
          />
        </div>
      </div>
    );
  }

  // Text input
  const value = state.filters[config.filterKey as keyof Filters] as string;

  return (
    <div className='space-y-1.5'>
      <label className='text-xs font-medium text-muted-foreground uppercase tracking-wide'>{config.label}</label>
      <Input value={value || ''} onChange={(e) => setFilters({ [config.filterKey]: e.target.value })} placeholder={`${config.label} eingeben...`} />
    </div>
  );
}

interface AutocompleteFilterProps {
  config: FilterConfig;
}

function AutocompleteFilter({ config }: AutocompleteFilterProps) {
  const { state, setFilters } = useSimplifyTable();
  const [open, setOpen] = useState(false);
  const [inputValue, setInputValue] = useState('');

  const options = config.options as DropdownOption[];
  const contextValues = state.filters[config.filterKey as keyof Filters] as string[];
  const [localSelectedValues, setLocalSelectedValues] = useState<string[]>(contextValues);

  // Keep local state in sync when context changes externally (e.g. preset apply / reset)
  const prevContextRef = useRef(contextValues);
  if (prevContextRef.current !== contextValues) {
    prevContextRef.current = contextValues;
    setLocalSelectedValues(contextValues);
  }

  const selectedValues = localSelectedValues;

  // Ref to track latest local values for syncing on close
  const localRef = useRef(localSelectedValues);
  localRef.current = localSelectedValues;

  // Sync context only when the popover closes
  const handleOpenChange = (isOpen: boolean) => {
    setOpen(isOpen);
    if (!isOpen) {
      setFilters({ [config.filterKey]: localRef.current });
    }
  };

  const filteredOptions = options.filter((opt) => {
    const matchesSearch = opt.label.toLowerCase().includes(inputValue.toLowerCase());
    return matchesSearch;
  });

  const handleToggle = (value: string) => {
    const isSelected = selectedValues.includes(value);
    const newValues = isSelected ? selectedValues.filter((v) => v !== value) : [...selectedValues, value];
    setLocalSelectedValues(newValues);
    setInputValue('');
  };

  const handleRemove = (value: string) => {
    const newValues = selectedValues.filter((v) => v !== value);
    setLocalSelectedValues(newValues);
    setFilters({ [config.filterKey]: newValues });
  };

  const handleSelectAll = () => {
    const allIds = options.map((opt) => opt.id);
    setLocalSelectedValues(allIds);
    setInputValue('');
  };

  const handleUnselectAll = () => {
    setLocalSelectedValues([]);
    setInputValue('');
  };

  const getLabel = (id: string) => {
    return options.find((opt) => opt.id === id)?.label || id;
  };

  const allSelected = options.length > 0 && selectedValues.length === options.length;

  // Marker badges (e.g. SPVs, Konzerngesellschaften): each badge selects/deselects
  // its tagged subset of options at once. An option may carry multiple markers.
  const markers = config.markers ?? [];
  const markerBuckets = markers.map((m) => {
    const ids = options.filter((o) => o.markers?.includes(m.id)).map((o) => o.id);
    return {
      meta: m,
      ids,
      allSelected: ids.length > 0 && ids.every((id) => selectedValues.includes(id)),
    };
  });

  const handleToggleMarker = (ids: string[], allSelectedForMarker: boolean) => {
    if (ids.length === 0) return;
    if (allSelectedForMarker) {
      setLocalSelectedValues(selectedValues.filter((v) => !ids.includes(v)));
    } else {
      const merged = Array.from(new Set([...selectedValues, ...ids]));
      setLocalSelectedValues(merged);
    }
    setInputValue('');
  };

  return (
    <div className='space-y-1.5'>
      <label className='text-xs font-medium text-muted-foreground uppercase tracking-wide'>{config.label}</label>
      <Popover open={open} onOpenChange={handleOpenChange}>
        <PopoverTrigger className='min-h-7 w-full rounded-md border border-input bg-input/20 px-2 py-1 text-xs ring-offset-background cursor-pointer flex gap-1 items-start text-left min-w-0'>
          <div className='flex max-h-16 flex-1 flex-wrap content-start items-start gap-1 overflow-y-auto pr-1 scrollbar-thumb-[#FFCC00] scrollbar-track-transparent'>
            {selectedValues.map((val) => (
              <Badge key={val} variant='secondary' className='gap-1 bg-muted text-muted-foreground h-4.5 shrink-0'>
                <span className='max-w-25 truncate text-2xs'>{getLabel(val)}</span>
                <span
                  role='button'
                  tabIndex={0}
                  onClick={(e) => {
                    e.stopPropagation();
                    e.preventDefault();
                    handleRemove(val);
                  }}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                      e.stopPropagation();
                      e.preventDefault();
                      handleRemove(val);
                    }
                  }}
                  className='hover:bg-primary-foreground/20 rounded-full p-0.5 cursor-pointer'
                >
                  <HugeiconsIcon icon={Cancel01Icon} size={10} />
                </span>
              </Badge>
            ))}
            {selectedValues.length === 0 && <span className='text-muted-foreground'>{config.label} suchen...</span>}
          </div>
        </PopoverTrigger>
        <PopoverContent className='w-(--anchor-width) p-0' align='start'>
          <Command>
            <CommandInput placeholder={`${config.label} suchen...`} value={inputValue} onValueChange={setInputValue} />
            <div className='flex items-center justify-between px-2 py-1.5 border-b'>
              <button
                type='button'
                className='text-xs text-primary hover:underline cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed'
                onClick={handleSelectAll}
                disabled={allSelected}
              >
                Alle auswählen
              </button>
              <button
                type='button'
                className='text-xs text-destructive hover:underline cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed'
                onClick={handleUnselectAll}
                disabled={selectedValues.length === 0}
              >
                Alle abwählen
              </button>
            </div>
            {markerBuckets.some((b) => b.ids.length > 0) && (
              <div className='flex flex-wrap gap-1 px-2 py-1.5 border-b'>
                {markerBuckets
                  .filter((b) => b.ids.length > 0)
                  .map((b) => (
                    <button
                      key={b.meta.id}
                      type='button'
                      onClick={() => handleToggleMarker(b.ids, b.allSelected)}
                      className='inline-flex items-center gap-1 rounded-full border px-1.5 py-px text-2xs font-medium transition-colors cursor-pointer hover:opacity-90'
                      style={
                        b.allSelected
                          ? { backgroundColor: b.meta.color, borderColor: b.meta.color, color: '#fff' }
                          : { backgroundColor: 'transparent', borderColor: b.meta.color, color: b.meta.color }
                      }
                      title={b.allSelected ? `${b.meta.label} abwählen` : `${b.meta.label} auswählen`}
                    >
                      {b.meta.label}
                      <span className='opacity-70'>({b.ids.length})</span>
                    </button>
                  ))}
              </div>
            )}
            <CommandList>
              <CommandEmpty>Keine Ergebnisse gefunden.</CommandEmpty>
              <CommandGroup>
                {filteredOptions.map((opt) => (
                  <CommandItem key={opt.id} value={opt.label} onSelect={() => handleToggle(opt.id)} className='flex items-center gap-2'>
                    <HugeiconsIcon icon={Tick01Icon} size={14} className={selectedValues.includes(opt.id) ? 'text-primary' : 'invisible'} />
                    <span className='flex-1 truncate'>{opt.label}</span>
                    {opt.markers && opt.markers.length > 0 && (
                      <span className='flex shrink-0 items-center gap-0.5'>
                        {opt.markers.map((mid) => {
                          const m = markers.find((x) => x.id === mid);
                          if (!m) return null;
                          return (
                            <span key={mid} className='inline-block h-2 w-2 rounded-full' style={{ backgroundColor: m.color }} aria-label={m.label} />
                          );
                        })}
                      </span>
                    )}
                  </CommandItem>
                ))}
              </CommandGroup>
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>
    </div>
  );
}
