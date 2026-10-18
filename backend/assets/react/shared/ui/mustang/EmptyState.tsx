import type {LucideIcon} from 'lucide-react';
import type {ReactNode} from 'react';

interface Props {
  icon: LucideIcon;
  /** One sentence: what will appear here. */
  title: string;
  description?: string;
  /** The one action that fills it. */
  action?: ReactNode;
}

/** An icon, one sentence and one action (DESIGN.md "States and feedback"). */
export function EmptyState({icon: Icon, title, description, action}: Props) {
  return (
    <div className="flex flex-col items-center gap-3 px-4 py-12 text-center">
      <span className="flex size-12 items-center justify-center rounded-full bg-muted">
        <Icon aria-hidden className="size-6 text-muted-foreground" />
      </span>
      <p className="font-medium">{title}</p>
      {description && (
        <p className="max-w-sm text-sm text-muted-foreground">{description}</p>
      )}
      {action}
    </div>
  );
}
