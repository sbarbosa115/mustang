import {Search, SlidersHorizontal, X} from 'lucide-react';
import {useEffect, useId, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {Schema} from '@/shared/api';
import {Badge} from '@/shared/ui/badge';
import {Button} from '@/shared/ui/button';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/ui/select';
import {
  SORTS,
  activeFilterCount,
  type InventoryFilters as Filters,
} from '../model/filters';

const ANY = '__any__';
const STATUSES = ['available', 'hold', 'sold', 'in_transit'] as const;
const CONDITIONS = ['new', 'used', 'cpo'] as const;
const SEARCH_DELAY_MS = 300;

interface Props {
  filters: Filters;
  facets?: Schema<'VehicleFacetsOutput'>;
  onChange: (next: Filters) => void;
  onClear: () => void;
}

/**
 * The search box, the sort order and the filter panel of the inventory (FR-21). It holds no data of its own: the
 * page keeps the filters in the URL and passes them back in.
 */
export function InventoryFilters({filters, facets, onChange, onClear}: Props) {
  const {t} = useTranslation('inventory');
  const [open, setOpen] = useState(false);
  const panelId = useId();
  const count = activeFilterCount(filters);

  const set = (patch: Partial<Filters>) => onChange({...filters, ...patch});

  return (
    <section aria-label={t('filters.label')} className="space-y-3">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
        <SearchBox
          value={filters.q}
          onCommit={(q) => set({q})}
          className="sm:max-w-sm sm:flex-1"
        />
        <div className="flex items-end gap-2">
          <div className="flex-1 space-y-1.5 sm:w-48 sm:flex-none">
            <Label htmlFor={`${panelId}-sort`}>{t('sort.label')}</Label>
            <Select value={filters.sort} onValueChange={(sort) => set({sort})}>
              <SelectTrigger
                id={`${panelId}-sort`}
                className="h-11 w-full sm:h-9"
              >
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {SORTS.map((sort) => (
                  <SelectItem key={sort} value={sort}>
                    {t(`sort.${sort}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <Button
            type="button"
            variant="outline"
            aria-expanded={open}
            aria-controls={panelId}
            onClick={() => setOpen((v) => !v)}
          >
            <SlidersHorizontal aria-hidden />
            {t('filters.toggle')}
            {count > 0 && (
              <Badge
                variant="secondary"
                aria-label={t('filters.active', {count})}
              >
                {count}
              </Badge>
            )}
          </Button>
        </div>
      </div>

      {open && (
        <div
          id={panelId}
          className="grid gap-3 rounded-lg border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4"
        >
          <Choice
            label={t('filters.status')}
            value={filters.status}
            onChange={(status) => set({status})}
            options={STATUSES.map((v) => ({value: v, label: t(`status.${v}`)}))}
          />
          <Choice
            label={t('filters.condition')}
            value={filters.condition}
            onChange={(condition) => set({condition})}
            options={CONDITIONS.map((v) => ({
              value: v,
              label: t(`condition.${v}`),
            }))}
          />
          <Choice
            label={t('filters.make')}
            value={filters.make}
            onChange={(make) => set({make})}
            options={facetOptions(facets?.makes, t)}
          />
          <Choice
            label={t('filters.model')}
            value={filters.model}
            onChange={(model) => set({model})}
            options={facetOptions(facets?.models, t)}
          />
          <Choice
            label={t('filters.body')}
            value={filters.body}
            onChange={(body) => set({body})}
            options={facetOptions(facets?.bodies, t)}
          />
          <Choice
            label={t('filters.drivetrain')}
            value={filters.drivetrain}
            onChange={(drivetrain) => set({drivetrain})}
            options={facetOptions(facets?.drivetrains, t)}
          />
          <Choice
            label={t('filters.fuel')}
            value={filters.fuel}
            onChange={(fuel) => set({fuel})}
            options={facetOptions(facets?.fuels, t)}
          />
          <Choice
            label={t('filters.color')}
            value={filters.color}
            onChange={(color) => set({color})}
            options={facetOptions(facets?.colors, t)}
          />
          <NumberField
            label={t('filters.year_from')}
            value={filters.yearMin}
            placeholder={facets?.year_min?.toString()}
            onCommit={(yearMin) => set({yearMin})}
          />
          <NumberField
            label={t('filters.year_to')}
            value={filters.yearMax}
            placeholder={facets?.year_max?.toString()}
            onCommit={(yearMax) => set({yearMax})}
          />
          <NumberField
            label={t('filters.price_from')}
            value={filters.priceMin}
            onCommit={(priceMin) => set({priceMin})}
          />
          <NumberField
            label={t('filters.price_to')}
            value={filters.priceMax}
            onCommit={(priceMax) => set({priceMax})}
          />
          <NumberField
            label={t('filters.mileage_max')}
            value={filters.mileageMax}
            onCommit={(mileageMax) => set({mileageMax})}
          />
          <div className="flex items-end sm:col-span-2 lg:col-span-3 lg:justify-end">
            <Button
              type="button"
              variant="ghost"
              onClick={onClear}
              disabled={count === 0 && filters.q === ''}
            >
              <X aria-hidden />
              {t('filters.clear')}
            </Button>
          </div>
        </div>
      )}
    </section>
  );
}

type Translate = (key: string, options?: Record<string, unknown>) => string;

function facetOptions(
  facets: Array<{value: string; count: number}> | undefined,
  t: Translate,
) {
  return (facets ?? []).map((f) => ({
    value: f.value,
    label: t('filters.option', {value: f.value, count: f.count}),
  }));
}

/** Types into a local box and tells the page once the typing pauses. */
function SearchBox({
  value,
  onCommit,
  className,
}: {
  value: string;
  onCommit: (value: string) => void;
  className?: string;
}) {
  const {t} = useTranslation('inventory');
  const [text, setText] = useState(value);
  const committed = useRef(value);
  const id = useId();

  // The page cleared or replaced the search (Clear filters, back button): follow it.
  useEffect(() => {
    if (value !== committed.current) {
      committed.current = value;
      setText(value);
    }
  }, [value]);

  useEffect(() => {
    if (text.trim() === committed.current.trim()) return;
    const timer = setTimeout(() => {
      committed.current = text;
      onCommit(text);
    }, SEARCH_DELAY_MS);
    return () => clearTimeout(timer);
  }, [text, onCommit]);

  return (
    <div className={className}>
      <Label htmlFor={id} className="mb-1.5">
        {t('search.label')}
      </Label>
      <div className="relative">
        <Search
          aria-hidden
          className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
          id={id}
          type="search"
          value={text}
          placeholder={t('search.placeholder')}
          className="pl-9"
          onChange={(event) => setText(event.target.value)}
        />
      </div>
    </div>
  );
}

function Choice({
  label,
  value,
  onChange,
  options,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  options: Array<{value: string; label: string}>;
}) {
  const {t} = useTranslation('inventory');
  const id = useId();
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{label}</Label>
      <Select
        value={value === '' ? ANY : value}
        onValueChange={(next) => onChange(next === ANY ? '' : next)}
      >
        <SelectTrigger id={id} className="h-11 w-full sm:h-9">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ANY}>{t('filters.any')}</SelectItem>
          {options.map((option) => (
            <SelectItem key={option.value} value={option.value}>
              {option.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}

/** A whole-number box that applies on blur or Enter, so the list does not reload on every digit. */
function NumberField({
  label,
  value,
  placeholder,
  onCommit,
}: {
  label: string;
  value: string;
  placeholder?: string;
  onCommit: (value: string) => void;
}) {
  const id = useId();
  const [text, setText] = useState(value);
  const [seen, setSeen] = useState(value);
  if (value !== seen) {
    // The page changed it (Clear filters, back button): follow it.
    setSeen(value);
    setText(value);
  }
  const commit = () => {
    const clean = text.replace(/\D/g, '');
    setText(clean);
    if (clean !== value) onCommit(clean);
  };
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{label}</Label>
      <Input
        id={id}
        inputMode="numeric"
        value={text}
        placeholder={placeholder}
        onChange={(event) => setText(event.target.value)}
        onBlur={commit}
        onKeyDown={(event) => {
          if (event.key === 'Enter') commit();
        }}
      />
    </div>
  );
}
