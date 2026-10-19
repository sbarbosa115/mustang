import {useQueryClient} from '@tanstack/react-query';
import {ArrowDown, ArrowUp} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {saveRouting, type Routing} from '@/entities/lead';
import {ApiError, type Schema} from '@/shared/api';
import {Button} from '@/shared/ui/button';
import {Checkbox} from '@/shared/ui/checkbox';

export const ROUTING_KEY = ['leads', 'routing'] as const;

interface Rep {
  id: string;
  name: string;
}

interface Props {
  routing: Routing;
  sales: Array<Schema<'UserOutput'>>;
}

/** The reps in rotation first, in order, then the salespeople left out. */
function initialRows(routing: Routing, sales: Rep[]) {
  const inRotation = routing.users.filter((u) =>
    sales.some((s) => s.id === u.id),
  );
  const rest = sales.filter((s) => !inRotation.some((u) => u.id === s.id));
  return {
    order: [...inRotation, ...rest].map((u) => ({id: u.id, name: u.name})),
    included: new Set(inRotation.map((u) => u.id)),
  };
}

/** Choose who takes round-robin leads and in which order; Save sends the order. */
export function RoutingEditor({routing, sales}: Props) {
  const {t, i18n} = useTranslation('leads');
  const queryClient = useQueryClient();
  const [state, setState] = useState(() => initialRows(routing, sales));
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const userIds = state.order
    .filter((r) => state.included.has(r.id))
    .map((r) => r.id);
  const changed =
    userIds.join(',') !== routing.users.map((u) => u.id).join(',');

  const move = (index: number, by: -1 | 1) =>
    setState((prev) => {
      const order = [...prev.order];
      const [row] = order.splice(index, 1);
      if (row) order.splice(index + by, 0, row);
      return {...prev, order};
    });
  const toggle = (id: string, on: boolean) =>
    setState((prev) => {
      const included = new Set(prev.included);
      if (on) included.add(id);
      else included.delete(id);
      return {...prev, included};
    });

  async function save() {
    setSaving(true);
    setError(null);
    try {
      const saved = await saveRouting({user_ids: userIds});
      queryClient.setQueryData(ROUTING_KEY, saved);
      setState(initialRows(saved, state.order));
      toast.success(t('routing.saved'));
    } catch (e) {
      const code = e instanceof ApiError ? e.code : '';
      setError(
        code && i18n.exists(`leads:error.${code}`)
          ? t(`error.${code}`)
          : t('common:error.generic'),
      );
    } finally {
      setSaving(false);
    }
  }

  const positions = new Map(userIds.map((id, i) => [id, i + 1]));
  return (
    <div className="space-y-4">
      <p role="status" className="text-sm">
        {routing.next
          ? t('routing.next', {name: routing.next.name})
          : t('routing.nobody')}
      </p>
      <ol className="divide-y rounded-md border">
        {state.order.map((rep, index) => {
          const included = state.included.has(rep.id);
          return (
            <li key={rep.id} className="flex min-h-11 items-center gap-3 px-3">
              <Checkbox
                id={`routing_${rep.id}`}
                checked={included}
                aria-label={t('routing.include', {name: rep.name})}
                onCheckedChange={(checked) => toggle(rep.id, checked === true)}
              />
              <label
                htmlFor={`routing_${rep.id}`}
                className="flex flex-1 items-center gap-2 py-2 text-sm"
              >
                <span className="tabular w-6 text-muted-foreground">
                  {included ? `${positions.get(rep.id)}.` : ''}
                </span>
                <span className="font-medium">{rep.name}</span>
                {!included && (
                  <span className="text-muted-foreground">
                    {t('routing.out')}
                  </span>
                )}
              </label>
              <Button
                variant="ghost"
                size="icon"
                aria-label={t('routing.move_up', {name: rep.name})}
                disabled={index === 0}
                onClick={() => move(index, -1)}
              >
                <ArrowUp />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                aria-label={t('routing.move_down', {name: rep.name})}
                disabled={index === state.order.length - 1}
                onClick={() => move(index, 1)}
              >
                <ArrowDown />
              </Button>
            </li>
          );
        })}
      </ol>
      {error && (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      )}
      <div className="flex justify-end">
        <Button disabled={!changed || saving} onClick={() => void save()}>
          {saving ? t('routing.saving') : t('routing.save')}
        </Button>
      </div>
    </div>
  );
}
