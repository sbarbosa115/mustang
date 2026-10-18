import {useQuery} from '@tanstack/react-query';
import {X} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {listUsers, usersKeys} from '@/entities/user';
import {cn} from '@/shared/lib/utils';
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
  EMPTY_AUDIT_FILTERS,
  ENTITY_TYPES,
  entityLabel,
  isAuditFiltered,
  type AuditFilterValues,
} from '../model/filters';

const ANY = 'any';

interface Props {
  value: AuditFilterValues;
  onChange: (next: AuditFilterValues) => void;
  className?: string;
}

/** Record type, who did it and a period (both days included); a record picked from the list shows as a chip. */
export function AuditFilters({value, onChange, className}: Props) {
  const {t} = useTranslation('audit');
  const users = useQuery({
    queryKey: usersKeys.list({per_page: 100}),
    queryFn: () => listUsers({per_page: 100}),
    staleTime: 5 * 60_000,
  });
  const set = (patch: Partial<AuditFilterValues>) =>
    onChange({...value, ...patch});

  return (
    <div
      role="group"
      aria-label={t('filters.label')}
      className={cn('space-y-3', className)}
    >
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div className="space-y-1.5">
          <Label htmlFor="audit_entity_type">{t('filters.entity_type')}</Label>
          <Select
            value={value.entityType || ANY}
            onValueChange={(next) =>
              set({entityType: next === ANY ? '' : next, entityId: ''})
            }
          >
            <SelectTrigger id="audit_entity_type" className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={ANY}>{t('filters.any_type')}</SelectItem>
              {ENTITY_TYPES.map((type) => (
                <SelectItem key={type} value={type}>
                  {t(`entity.${type}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="audit_user">{t('filters.user')}</Label>
          <Select
            value={value.userId || ANY}
            onValueChange={(next) => set({userId: next === ANY ? '' : next})}
          >
            <SelectTrigger id="audit_user" className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={ANY}>{t('filters.anyone')}</SelectItem>
              {(users.data?.items ?? []).map((user) => (
                <SelectItem key={user.id} value={user.id}>
                  {user.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="audit_from">{t('filters.from')}</Label>
          <Input
            id="audit_from"
            type="date"
            value={value.from}
            max={value.to || undefined}
            onChange={(event) => set({from: event.target.value})}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="audit_to">{t('filters.to')}</Label>
          <Input
            id="audit_to"
            type="date"
            value={value.to}
            min={value.from || undefined}
            onChange={(event) => set({to: event.target.value})}
          />
        </div>
      </div>
      {isAuditFiltered(value) && (
        <div className="flex flex-wrap items-center gap-2">
          {value.entityId && (
            <span className="inline-flex items-center gap-1 rounded-md border bg-muted px-2 py-1 text-sm">
              {t('list.history_of', {
                type: entityLabel(t, value.entityType),
                id: value.entityId.slice(0, 8),
              })}
              <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={t('filters.remove_record')}
                onClick={() => set({entityId: ''})}
              >
                <X aria-hidden />
              </Button>
            </span>
          )}
          <Button
            type="button"
            variant="ghost"
            onClick={() => onChange(EMPTY_AUDIT_FILTERS)}
          >
            {t('filters.clear')}
          </Button>
        </div>
      )}
    </div>
  );
}
