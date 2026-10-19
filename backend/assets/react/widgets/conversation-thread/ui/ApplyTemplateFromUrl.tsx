import {useQuery} from '@tanstack/react-query';
import {useEffect, useRef} from 'react';
import {useSearchParams} from 'react-router';
import {listTemplates, templatesKeys} from '@/entities/template';
import type {ComposerDraft} from '@/features/send-message';

interface Props {
  channel: 'text' | 'email';
  /** The composer cannot take text (no consent or contact): nothing is filled. */
  disabled: boolean;
  /** The customer and the appointment are loaded, so the placeholders can be filled. */
  ready: boolean;
  insert: (draft: ComposerDraft) => void;
}

const slug = (name: string) => name.toLowerCase().replaceAll(' ', '-');

/**
 * Opened with ?template=<name-as-dashes>, puts that active template of the composer's channel into the composer, as
 * choosing it from Templates does, then drops the parameters so a reload does not fill it again. Nothing is sent.
 */
export function ApplyTemplateFromUrl({
  channel,
  disabled,
  ready,
  insert,
}: Props) {
  const [params, setParams] = useSearchParams();
  const wanted = params.get('template');
  const done = useRef(false);
  const query = {channel, active: true};
  const templates = useQuery({
    queryKey: templatesKeys.list(query),
    queryFn: () => listTemplates(query),
    enabled: wanted !== null && !disabled,
    staleTime: 60_000,
  });

  useEffect(() => {
    if (wanted === null || done.current || !ready) return;
    if (!disabled && templates.isPending) return;
    done.current = true;
    const template = disabled
      ? undefined
      : templates.data?.items.find(
          (item) =>
            item.active &&
            item.channel === channel &&
            slug(item.name) === wanted,
        );
    if (template) {
      insert({
        body: template.body,
        subject: template.subject,
        templateId: template.id,
      });
    }
    setParams(
      (current) => {
        const next = new URLSearchParams(current);
        next.delete('template');
        next.delete('appointment');
        return next;
      },
      {replace: true},
    );
  }, [
    wanted,
    ready,
    disabled,
    templates.isPending,
    templates.data,
    channel,
    insert,
    setParams,
  ]);

  return null;
}
