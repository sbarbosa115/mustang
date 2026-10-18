import {useQuery} from '@tanstack/react-query';
import {FileText} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  listTemplates,
  templatesKeys,
  TemplatePicker,
  type Template,
} from '@/entities/template';
import {Button} from '@/shared/ui/button';
import {Popover, PopoverContent, PopoverTrigger} from '@/shared/ui/popover';
import {Skeleton} from '@/shared/ui/skeleton';

interface Props {
  channel: 'text' | 'email';
  disabled?: boolean;
  /** The template chosen; the composer fills its placeholders. */
  onPick: (template: Template) => void;
}

/** "Templates": the active templates of the composer's channel, to insert one (CRM-09). */
export function PickTemplateButton({channel, disabled, onPick}: Props) {
  const {t} = useTranslation('inbox');
  const [open, setOpen] = useState(false);
  const query = {channel, active: true};
  const templates = useQuery({
    queryKey: templatesKeys.list(query),
    queryFn: () => listTemplates(query),
    enabled: open,
    staleTime: 60_000,
  });

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button type="button" variant="outline" size="sm" disabled={disabled}>
          <FileText aria-hidden />
          {t('templates.open')}
        </Button>
      </PopoverTrigger>
      <PopoverContent
        align="start"
        className="w-80 p-0"
        aria-label={t('templates.title')}
      >
        <p className="border-b px-3 py-2 text-sm font-medium">
          {t('templates.for_channel', {
            channel: t(`common:channel.${channel}`),
          })}
        </p>
        {templates.isPending ? (
          <div className="space-y-2 p-3" aria-busy="true">
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-10 w-full" />
          </div>
        ) : (
          <TemplatePicker
            templates={(templates.data?.items ?? []).filter(
              (template) => template.active && template.channel === channel,
            )}
            onPick={(template) => {
              setOpen(false);
              onPick(template);
            }}
          />
        )}
      </PopoverContent>
    </Popover>
  );
}
