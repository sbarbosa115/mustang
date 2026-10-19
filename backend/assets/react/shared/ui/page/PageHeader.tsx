import type {ReactNode} from 'react';

interface Props {
  title: string;
  description?: string;
  /** The page's one primary action, and any secondary ones. */
  actions?: ReactNode;
  back?: ReactNode;
}

/** The H1 of a page with its actions on the right (on phones, under the title). */
export function PageHeader({title, description, actions, back}: Props) {
  return (
    <header className="mb-4 flex flex-col gap-3 sm:mb-6 sm:flex-row sm:items-end sm:justify-between">
      <div className="space-y-1">
        {back}
        <h1 className="text-2xl leading-8 font-semibold">{title}</h1>
        {description && (
          <p className="text-sm text-muted-foreground">{description}</p>
        )}
      </div>
      {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
    </header>
  );
}
