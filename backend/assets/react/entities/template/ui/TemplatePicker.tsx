import {FileText} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import type {Schema} from '@/shared/api';
import {Button} from '@/shared/ui/button';

interface Props {
  templates: Array<Schema<'TemplateOutput'>>;
  onPick: (template: Schema<'TemplateOutput'>) => void;
}

/** The list of templates to choose from in the composer; filtering by channel is the caller's. */
export function TemplatePicker({templates, onPick}: Props) {
  const {t} = useTranslation('templates');
  if (templates.length === 0) {
    return (
      <p className="p-3 text-sm text-muted-foreground">{t('picker.empty')}</p>
    );
  }
  return (
    <ul className="max-h-72 overflow-y-auto p-1">
      {templates.map((template) => (
        <li key={template.id}>
          <Button
            variant="ghost"
            className="h-auto w-full justify-start py-2 text-left"
            onClick={() => onPick(template)}
          >
            <FileText aria-hidden className="mt-0.5 self-start" />
            <span className="min-w-0">
              <span className="block font-medium">{template.name}</span>
              <span className="block truncate text-xs text-muted-foreground">
                {template.body}
              </span>
            </span>
          </Button>
        </li>
      ))}
    </ul>
  );
}
