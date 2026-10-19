import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Button} from '@/shared/ui/button';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import {
  PRESET_DAYS,
  isValidRange,
  lastDays,
  presetOf,
  type Range,
} from '../model/period';

interface Props {
  value: Range;
  /** Today on the dealership's clock (yyyy-MM-dd). */
  today: string;
  onChange: (range: Range) => void;
}

/**
 * Quick ranges and two dates; the change is sent only once the pair is a valid period.
 */
export function PeriodPicker({value, today, onChange}: Props) {
  const {t} = useTranslation('reports');
  const [from, setFrom] = useState(value.from);
  const [to, setTo] = useState(value.to);
  // A new period from outside (a quick range, the address) replaces the drafts; typing here never does.
  const [seen, setSeen] = useState(value);
  if (seen.from !== value.from || seen.to !== value.to) {
    setSeen(value);
    setFrom(value.from);
    setTo(value.to);
  }

  const valid = isValidRange(from, to);
  const preset = presetOf(value, today);
  const edit = (next: Range) => {
    setFrom(next.from);
    setTo(next.to);
    if (isValidRange(next.from, next.to)) onChange(next);
  };

  return (
    <section
      aria-label={t('period.label')}
      className="flex flex-wrap items-end gap-3"
    >
      <div role="group" aria-label={t('period.presets')} className="flex gap-2">
        {PRESET_DAYS.map((days) => (
          <Button
            key={days}
            type="button"
            variant={preset === days ? 'secondary' : 'outline'}
            aria-pressed={preset === days}
            onClick={() => onChange(lastDays(days, today))}
          >
            {t(`period.last${days}`)}
          </Button>
        ))}
      </div>
      <div className="space-y-1">
        <Label htmlFor="period-from">{t('period.from')}</Label>
        <Input
          id="period-from"
          type="date"
          value={from}
          max={to || undefined}
          aria-invalid={!valid}
          onChange={(e) => edit({from: e.target.value, to})}
        />
      </div>
      <div className="space-y-1">
        <Label htmlFor="period-to">{t('period.to')}</Label>
        <Input
          id="period-to"
          type="date"
          value={to}
          min={from || undefined}
          aria-invalid={!valid}
          onChange={(e) => edit({from, to: e.target.value})}
        />
      </div>
      {!valid && (
        <p role="alert" className="text-sm text-danger">
          {t('period.invalid')}
        </p>
      )}
    </section>
  );
}
