import {useQuery, useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {updateAppointment, type Appointment} from '@/entities/appointment';
import {listUsers, useAuth, usersKeys} from '@/entities/user';
import {formatDateTime, localToUtc} from '@/shared/lib/dates';
import {formatInTimeZone} from 'date-fns-tz';
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/ui/select';
import {appointmentChanged} from '../lib/cache';
import {errorText} from '../lib/errors';

interface Props {
  appointment: Appointment;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/** Move an appointment to another time and/or hand it to another rep. */
export function RescheduleDialog(props: Props) {
  return (
    <Dialog open={props.open} onOpenChange={props.onOpenChange}>
      <DialogContent>
        {/* The form is mounted on every open, so it starts from the record as it is now. */}
        <RescheduleDialogBody {...props} />
      </DialogContent>
    </Dialog>
  );
}

function RescheduleDialogBody({appointment, open, onOpenChange}: Props) {
  const {t, i18n} = useTranslation('appointments');
  const {me} = useAuth();
  const timeZone = me?.dealership.timezone ?? 'America/New_York';
  const queryClient = useQueryClient();
  const [when, setWhen] = useState(
    formatInTimeZone(appointment.scheduled_at, timeZone, "yyyy-MM-dd'T'HH:mm"),
  );
  const [userId, setUserId] = useState(appointment.user.id);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const users = useQuery({
    queryKey: usersKeys.list({per_page: 100}),
    queryFn: () => listUsers({per_page: 100}),
    enabled: open,
  });
  const reps = (users.data?.items ?? []).filter(
    (u) => u.active || u.id === appointment.user.id,
  );

  const unchangedTime =
    when ===
    formatInTimeZone(appointment.scheduled_at, timeZone, "yyyy-MM-dd'T'HH:mm");
  const past = when !== '' && localToUtc(when, timeZone) <= new Date();
  // Handing a past appointment to another rep keeps its time, which is fine; picking a past time is not.
  const invalid = when === '' || (!unchangedTime && past);
  const unchanged = unchangedTime && userId === appointment.user.id;

  async function save(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setSaving(true);
    try {
      const updated = await updateAppointment(appointment.id, {
        scheduled_at: unchangedTime ? null : when,
        user_id: userId === appointment.user.id ? null : userId,
        duration_minutes: null,
        kind: null,
        note: null,
      });
      appointmentChanged(queryClient, updated);
      toast.success(
        t('reschedule_dialog.done', {
          name: updated.customer.name,
          when: formatDateTime(updated.scheduled_at, timeZone),
        }),
      );
      onOpenChange(false);
    } catch (e) {
      setError(errorText(t, (k) => i18n.exists(k), e));
    } finally {
      setSaving(false);
    }
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>
          {t('reschedule_dialog.title', {name: appointment.customer.name})}
        </DialogTitle>
        <DialogDescription>
          {t('reschedule_dialog.description')}
        </DialogDescription>
      </DialogHeader>
      <form onSubmit={(e) => void save(e)} className="space-y-4" noValidate>
        <div className="space-y-1.5">
          <Label htmlFor="reschedule_when">{t('reschedule_dialog.when')}</Label>
          <Input
            id="reschedule_when"
            type="datetime-local"
            value={when}
            aria-invalid={past || undefined}
            onChange={(e) => setWhen(e.target.value)}
          />
          {past && !unchangedTime && (
            <p role="alert" className="text-sm text-danger">
              {t('book_dialog.when_past')}
            </p>
          )}
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="reschedule_rep">{t('reschedule_dialog.rep')}</Label>
          <Select value={userId} onValueChange={setUserId}>
            <SelectTrigger id="reschedule_rep" className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {!reps.some((u) => u.id === appointment.user.id) && (
                <SelectItem value={appointment.user.id}>
                  {appointment.user.name}
                </SelectItem>
              )}
              {reps.map((u) => (
                <SelectItem key={u.id} value={u.id}>
                  {u.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        {error && (
          <p role="alert" className="text-sm text-danger">
            {error}
          </p>
        )}
        <DialogFooter className="gap-2">
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
          >
            {t('common:action.cancel')}
          </Button>
          <Button type="submit" disabled={saving || invalid || unchanged}>
            {t('reschedule_dialog.submit')}
          </Button>
        </DialogFooter>
      </form>
    </>
  );
}
