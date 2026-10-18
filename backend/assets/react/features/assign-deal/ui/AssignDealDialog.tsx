import {useQuery, useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {assignDeal, type Deal} from '@/entities/deal';
import {listUsers, useAuth, usersKeys} from '@/entities/user';
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/ui/select';
import {dealChanged} from '../lib/cache';
import {errorText} from '../lib/errors';

interface Props {
  deal: Deal;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/** Give the deal to a rep of the dealership, or take it ("Assign to me"). */
export function AssignDealDialog(props: Props) {
  return (
    <Dialog open={props.open} onOpenChange={props.onOpenChange}>
      <DialogContent>
        {/* The form is mounted on every open, so it starts from the record as it is now. */}
        <AssignDealDialogBody {...props} />
      </DialogContent>
    </Dialog>
  );
}

function AssignDealDialogBody({deal, open, onOpenChange}: Props) {
  const {t, i18n} = useTranslation('deals');
  const {me} = useAuth();
  const queryClient = useQueryClient();
  const [userId, setUserId] = useState(deal.assignee?.id ?? '');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const users = useQuery({
    queryKey: usersKeys.list({per_page: 100}),
    queryFn: () => listUsers({per_page: 100}),
    enabled: open,
  });

  async function assign(id: string) {
    setError(null);
    setSaving(true);
    try {
      const updated = await assignDeal(deal.id, {user_id: id});
      dealChanged(queryClient, updated);
      toast.success(
        t('assign_dialog.saved', {name: updated.assignee?.name ?? ''}),
      );
      onOpenChange(false);
    } catch (e) {
      setError(errorText(t, (k) => i18n.exists(k), e));
    } finally {
      setSaving(false);
    }
  }

  const people = (users.data?.items ?? []).filter(
    (u) => u.active || u.id === deal.assignee?.id,
  );
  const myId = me?.user.id;
  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('assign_dialog.title')}</DialogTitle>
        <DialogDescription>{t('assign_dialog.description')}</DialogDescription>
      </DialogHeader>
      <form
        className="space-y-4"
        noValidate
        onSubmit={(e) => {
          e.preventDefault();
          if (userId) void assign(userId);
        }}
      >
        <div className="space-y-1.5">
          <Label htmlFor="deal_assignee">{t('assign_dialog.user')}</Label>
          <Select value={userId} onValueChange={setUserId}>
            <SelectTrigger id="deal_assignee" className="w-full">
              <SelectValue placeholder={t('summary.unassigned')} />
            </SelectTrigger>
            <SelectContent>
              {deal.assignee &&
                !people.some((u) => u.id === deal.assignee?.id) && (
                  <SelectItem value={deal.assignee.id}>
                    {deal.assignee.name}
                  </SelectItem>
                )}
              {people.map((u) => (
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
          {myId && myId !== deal.assignee?.id && (
            <Button
              type="button"
              variant="outline"
              className="sm:mr-auto"
              disabled={saving}
              onClick={() => void assign(myId)}
            >
              {t('assign_dialog.assign_to_me')}
            </Button>
          )}
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
          >
            {t('common:action.cancel')}
          </Button>
          <Button
            type="submit"
            disabled={saving || !userId || userId === deal.assignee?.id}
          >
            {t('common:action.save')}
          </Button>
        </DialogFooter>
      </form>
    </>
  );
}
