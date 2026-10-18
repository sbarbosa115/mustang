import {useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {changeStage, type Deal} from '@/entities/deal';
import {Button} from '@/shared/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/ui/dialog';
import {Label} from '@/shared/ui/label';
import {StageBadge, type DealStage} from '@/shared/ui/mustang';
import {RadioGroup, RadioGroupItem} from '@/shared/ui/radio-group';
import {Textarea} from '@/shared/ui/textarea';
import {dealChanged} from '../lib/cache';
import {errorText} from '../lib/errors';

const STAGES: DealStage[] = [
  'new',
  'engaged',
  'appointment',
  'visit',
  'proposal',
  'sold',
  'delivered',
  'lost',
];

interface Props {
  deal: Deal;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/** Move a deal forward or back; lost asks why; a lost deal only reopens to engaged. */
export function StageDialog(props: Props) {
  return (
    <Dialog open={props.open} onOpenChange={props.onOpenChange}>
      <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
        {/* The form is mounted on every open, so it starts from the record as it is now. */}
        <StageDialogBody {...props} />
      </DialogContent>
    </Dialog>
  );
}

function StageDialogBody({deal, onOpenChange}: Props) {
  const {t, i18n} = useTranslation('deals');
  const queryClient = useQueryClient();
  const reopening = deal.stage === 'lost';
  const options = STAGES.filter((s) => s !== deal.stage);
  const [stage, setStage] = useState<DealStage | ''>('');
  const [reason, setReason] = useState('');
  const [reasonError, setReasonError] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const target: DealStage | '' = reopening ? 'engaged' : stage;

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    if (!target) return;
    if (target === 'lost' && reason.trim() === '') {
      setReasonError(true);
      return;
    }
    setError(null);
    setSaving(true);
    try {
      const updated = await changeStage(deal.id, {
        stage: target,
        lost_reason: target === 'lost' ? reason.trim() : null,
      });
      dealChanged(queryClient, updated);
      toast.success(
        t('stage_dialog.moved', {stage: t(`common:stage.${target}`)}),
      );
      onOpenChange(false);
    } catch (e) {
      setError(errorText(t, (k) => i18n.exists(k), e));
    } finally {
      setSaving(false);
    }
  }

  const name = deal.customer.name;
  return (
    <>
      <DialogHeader>
        <DialogTitle>
          {reopening
            ? t('stage_dialog.reopen_title', {name})
            : t('stage_dialog.title', {name})}
        </DialogTitle>
        <DialogDescription>
          {reopening
            ? t('stage_dialog.reopen_description')
            : t('stage_dialog.description')}
        </DialogDescription>
      </DialogHeader>
      <form onSubmit={(e) => void submit(e)} className="space-y-4" noValidate>
        <div>
          <StageBadge stage={deal.stage} daysInStage={deal.days_in_stage} />
        </div>
        {!reopening && (
          <fieldset className="space-y-2">
            <legend className="mb-2 text-sm font-medium">
              {t('stage_dialog.stage')}
            </legend>
            <RadioGroup
              value={stage}
              onValueChange={(v) => setStage(v as DealStage)}
              className="grid-cols-2 gap-2"
            >
              {options.map((s) => (
                <Label
                  key={s}
                  htmlFor={`stage_${s}`}
                  className="flex min-h-11 cursor-pointer items-center gap-3 rounded-md border px-3 has-[[data-state=checked]]:border-primary"
                >
                  <RadioGroupItem id={`stage_${s}`} value={s} />
                  {t(`common:stage.${s}`)}
                </Label>
              ))}
            </RadioGroup>
          </fieldset>
        )}
        {target === 'lost' && (
          <div className="space-y-1.5">
            <Label htmlFor="lost_reason">{t('stage_dialog.lost_reason')}</Label>
            <Textarea
              id="lost_reason"
              value={reason}
              maxLength={255}
              placeholder={t('stage_dialog.lost_reason_placeholder')}
              aria-invalid={reasonError || undefined}
              aria-describedby={reasonError ? 'lost_reason_error' : undefined}
              onChange={(e) => {
                setReason(e.target.value);
                setReasonError(false);
              }}
            />
            {reasonError && (
              <p id="lost_reason_error" className="text-sm text-danger">
                {t('stage_dialog.lost_reason_required')}
              </p>
            )}
          </div>
        )}
        {error && (
          <p role="alert" className="text-sm text-danger">
            {error}
          </p>
        )}
        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
          >
            {t('common:action.cancel')}
          </Button>
          <Button type="submit" disabled={!target || saving}>
            {reopening ? t('stage_dialog.reopen') : t('stage_dialog.submit')}
          </Button>
        </DialogFooter>
      </form>
    </>
  );
}
