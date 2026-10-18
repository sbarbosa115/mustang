import {useQuery, useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {assignLead, leadsKeys, type Lead} from '@/entities/lead';
import {listUsers, usersKeys} from '@/entities/user';
import {Button} from '@/shared/ui/button';
import {Label} from '@/shared/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/ui/select';
import {errorText} from '../lib/errors';

const LEAD_ROLES = ['sales', 'bdc', 'manager'];

interface Props {
  lead: Lead;
}

/** Hands a lead to another rep: the active salespeople, BDC agents and managers of the dealership. */
export function AssignLeadControl({lead}: Props) {
  const {t, i18n} = useTranslation('leads');
  const queryClient = useQueryClient();
  const [userId, setUserId] = useState(lead.assignee?.id ?? '');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const users = useQuery({
    queryKey: usersKeys.list({per_page: 100}),
    queryFn: () => listUsers({per_page: 100}),
  });
  const reps = (users.data?.items ?? []).filter(
    (u) => u.active && LEAD_ROLES.includes(u.role),
  );
  const closed = lead.status === 'closed';

  async function assign() {
    setBusy(true);
    setError(null);
    try {
      const updated = await assignLead(lead.id, {user_id: userId});
      queryClient.setQueryData(leadsKeys.detail(lead.id), updated);
      void queryClient.invalidateQueries({queryKey: ['leads', 'list']});
      toast.success(t('assign.assigned', {name: updated.assignee?.name}));
    } catch (e) {
      setError(errorText(t, (k) => i18n.exists(k), e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-1.5">
      <Label htmlFor="lead_assign">{t('assign.label')}</Label>
      <div className="flex gap-2">
        <Select value={userId} onValueChange={setUserId} disabled={closed}>
          <SelectTrigger id="lead_assign" className="w-full sm:w-64">
            <SelectValue placeholder={t('assign.placeholder')} />
          </SelectTrigger>
          <SelectContent>
            {reps.map((u) => (
              <SelectItem key={u.id} value={u.id}>
                {u.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Button
          variant="outline"
          disabled={
            closed || busy || userId === '' || userId === lead.assignee?.id
          }
          onClick={() => void assign()}
        >
          {t('assign.submit')}
        </Button>
      </div>
      {error && (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      )}
    </div>
  );
}
