import {useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {setTradeIn, type Deal} from '@/entities/deal';
import {Button} from '@/shared/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/ui/dialog';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import {dealChanged} from '../lib/cache';
import {errorText} from '../lib/errors';

type Field = 'year' | 'make' | 'model' | 'mileage' | 'payoff';
type Values = Record<Field, string>;

const EMPTY = {
  year: null,
  make: null,
  model: null,
  mileage: null,
  payoff_cents: null,
};

interface Props {
  deal: Deal;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

function wholeNumber(value: string): number | null | 'invalid' {
  const v = value.replace(/[,\s$]/g, '');
  if (v === '') return null;
  return /^\d+$/.test(v) ? Number(v) : 'invalid';
}

/** What the customer trades in: year, make, model, mileage and payoff; all empty removes it. */
export function TradeInDialog(props: Props) {
  return (
    <Dialog open={props.open} onOpenChange={props.onOpenChange}>
      <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
        {/* The form is mounted on every open, so it starts from the record as it is now. */}
        <TradeInDialogBody {...props} />
      </DialogContent>
    </Dialog>
  );
}

function TradeInDialogBody({deal, onOpenChange}: Props) {
  const {t, i18n} = useTranslation('deals');
  const queryClient = useQueryClient();
  const [values, setValues] = useState<Values>(() => {
    const ti = deal.trade_in;
    return {
      year: ti?.year != null ? String(ti.year) : '',
      make: ti?.make ?? '',
      model: ti?.model ?? '',
      mileage: ti?.mileage != null ? String(ti.mileage) : '',
      payoff:
        ti?.payoff_cents != null
          ? String(Math.round(ti.payoff_cents / 100))
          : '',
    };
  });
  const [invalid, setInvalid] = useState<Partial<Record<Field, string>>>({});
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  async function send(
    body: Parameters<typeof setTradeIn>[1],
    removed: boolean,
  ) {
    setError(null);
    setSaving(true);
    try {
      const updated = await setTradeIn(deal.id, body);
      dealChanged(queryClient, updated);
      toast.success(
        removed ? t('trade_in_dialog.removed') : t('trade_in_dialog.saved'),
      );
      onOpenChange(false);
    } catch (e) {
      setError(errorText(t, (k) => i18n.exists(k), e));
    } finally {
      setSaving(false);
    }
  }

  function submit(event: React.FormEvent) {
    event.preventDefault();
    const year = wholeNumber(values.year);
    const mileage = wholeNumber(values.mileage);
    const payoff = wholeNumber(values.payoff);
    const problems: Partial<Record<Field, string>> = {};
    if (year === 'invalid' || (year !== null && (year < 1950 || year > 2100))) {
      problems.year = t('trade_in_dialog.invalid_year');
    }
    if (mileage === 'invalid') {
      problems.mileage = t('trade_in_dialog.invalid_number');
    }
    if (payoff === 'invalid') {
      problems.payoff = t('trade_in_dialog.invalid_number');
    }
    setInvalid(problems);
    if (Object.keys(problems).length > 0) return;
    const body = {
      year: year as number | null,
      make: values.make.trim() || null,
      model: values.model.trim() || null,
      mileage: mileage as number | null,
      payoff_cents: payoff === null ? null : (payoff as number) * 100,
    };
    void send(
      body,
      Object.values(body).every((v) => v === null),
    );
  }

  const field = (name: Field, inputMode?: 'numeric') => (
    <div className="space-y-1.5">
      <Label htmlFor={`trade_in_${name}`}>{t(`trade_in_dialog.${name}`)}</Label>
      <Input
        id={`trade_in_${name}`}
        value={values[name]}
        inputMode={inputMode}
        maxLength={inputMode ? 12 : 60}
        aria-invalid={invalid[name] ? true : undefined}
        aria-describedby={invalid[name] ? `trade_in_${name}_error` : undefined}
        onChange={(e) => setValues((v) => ({...v, [name]: e.target.value}))}
      />
      {invalid[name] && (
        <p id={`trade_in_${name}_error`} className="text-sm text-danger">
          {invalid[name]}
        </p>
      )}
    </div>
  );

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('trade_in_dialog.title')}</DialogTitle>
        <DialogDescription>
          {t('trade_in_dialog.description')}
        </DialogDescription>
      </DialogHeader>
      <form onSubmit={submit} className="space-y-4" noValidate>
        <div className="grid gap-4 sm:grid-cols-3">
          {field('year', 'numeric')}
          {field('make')}
          {field('model')}
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          {field('mileage', 'numeric')}
          {field('payoff', 'numeric')}
        </div>
        {error && (
          <p role="alert" className="text-sm text-danger">
            {error}
          </p>
        )}
        <DialogFooter className="gap-2">
          {deal.trade_in && (
            <Button
              type="button"
              variant="ghost"
              className="sm:mr-auto"
              disabled={saving}
              onClick={() => void send(EMPTY, true)}
            >
              {t('trade_in_dialog.remove')}
            </Button>
          )}
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
          >
            {t('common:action.cancel')}
          </Button>
          <Button type="submit" disabled={saving}>
            {t('common:action.save')}
          </Button>
        </DialogFooter>
      </form>
    </>
  );
}
