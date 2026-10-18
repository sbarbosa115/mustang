import {Plus} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {useNavigate, useSearchParams} from 'react-router';
import {CaptureLeadDialog} from '@/features/capture-lead';
import {Button} from '@/shared/ui/button';
import {PageHeader} from '@/shared/ui/page';
import {LeadList} from '@/widgets/lead-list';

/** /leads: the list, and "Capture lead" (also opened by /leads?new=1). */
export function LeadsPage() {
  const {t} = useTranslation('leads');
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const capturing = params.get('new') === '1';

  const setCapturing = (open: boolean) =>
    setParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        if (open) next.set('new', '1');
        else next.delete('new');
        return next;
      },
      {replace: true},
    );

  return (
    <>
      <PageHeader
        title={t('list.title')}
        actions={
          <Button onClick={() => setCapturing(true)}>
            <Plus /> {t('capture.open')}
          </Button>
        }
      />
      <LeadList
        emptyAction={
          <Button variant="outline" onClick={() => setCapturing(true)}>
            <Plus /> {t('capture.open')}
          </Button>
        }
      />
      <CaptureLeadDialog
        open={capturing}
        onOpenChange={setCapturing}
        onCaptured={(lead) => void navigate(`/leads/${lead.id}`)}
      />
    </>
  );
}
