import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {StickyNote} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {timelineKeys} from '@/entities/activity';
import {addNote, listNotes} from '@/entities/customer';
import {Button} from '@/shared/ui/button';
import {Card, CardContent, CardHeader, CardTitle} from '@/shared/ui/card';
import {EmptyState, RelativeTime} from '@/shared/ui/mustang';
import {Skeleton} from '@/shared/ui/skeleton';
import {Textarea} from '@/shared/ui/textarea';
import {errorText} from '../lib/errors';

const notesKey = (customerId: string) =>
  ['customers', 'notes', customerId] as const;

/** The notes on a customer, newest first, with a box to add one. */
export function NotesPanel({customerId}: {customerId: string}) {
  const {t, i18n} = useTranslation('customers');
  const queryClient = useQueryClient();
  const [body, setBody] = useState('');
  const notes = useQuery({
    queryKey: notesKey(customerId),
    queryFn: () => listNotes(customerId),
  });
  const add = useMutation({
    mutationFn: (text: string) => addNote(customerId, {body: text}),
    onSuccess: (_note, text) => {
      // Only the text that was saved is cleared: what was typed meanwhile for the next note stays.
      setBody((current) => (current.trim() === text ? '' : current));
      void queryClient.invalidateQueries({queryKey: notesKey(customerId)});
      // The note lands on the customer's timeline too; a timeline cached while empty must not stay empty.
      void queryClient.invalidateQueries({queryKey: timelineKeys.all});
    },
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('notes.title')}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <form
          className="space-y-2"
          onSubmit={(event) => {
            event.preventDefault();
            if (body.trim() !== '') add.mutate(body.trim());
          }}
        >
          <label htmlFor="new_note" className="sr-only">
            {t('notes.label')}
          </label>
          <Textarea
            id="new_note"
            value={body}
            maxLength={5000}
            placeholder={t('notes.placeholder')}
            onChange={(event) => setBody(event.target.value)}
          />
          {add.isError && (
            <p role="alert" className="text-sm text-danger">
              {errorText(t, (k) => i18n.exists(k), add.error)}
            </p>
          )}
          <Button type="submit" disabled={body.trim() === '' || add.isPending}>
            {add.isPending ? t('notes.adding') : t('notes.add')}
          </Button>
        </form>
        {notes.isLoading ? (
          <div
            className="space-y-2"
            aria-busy="true"
            aria-label={t('common:list.loading')}
          >
            <Skeleton className="h-12 w-full" />
            <Skeleton className="h-12 w-full" />
          </div>
        ) : notes.isError ? (
          <div className="flex items-center justify-between gap-2 text-sm">
            <p role="alert" className="text-danger">
              {t('notes.error')}
            </p>
            <Button
              variant="outline"
              size="sm"
              onClick={() => void notes.refetch()}
            >
              {t('common:action.retry')}
            </Button>
          </div>
        ) : notes.data && notes.data.length > 0 ? (
          <ul className="space-y-3">
            {notes.data.map((note) => (
              <li
                key={note.id}
                className="space-y-1 rounded-md bg-muted p-3 text-sm"
              >
                <p className="whitespace-pre-wrap">{note.body}</p>
                <p className="flex items-center gap-2 text-xs text-muted-foreground">
                  {note.author && (
                    <span>{t('notes.by', {name: note.author.name})}</span>
                  )}
                  <RelativeTime iso={note.created_at} />
                </p>
              </li>
            ))}
          </ul>
        ) : (
          <EmptyState icon={StickyNote} title={t('notes.empty')} />
        )}
      </CardContent>
    </Card>
  );
}
