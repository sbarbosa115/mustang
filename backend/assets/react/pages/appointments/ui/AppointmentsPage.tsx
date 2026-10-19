import {Plus} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useAuth} from '@/entities/user';
import {BookAppointmentDialog} from '@/features/book-appointment';
import {Button} from '@/shared/ui/button';
import {PageHeader} from '@/shared/ui/page';
import {AppointmentCalendar} from '@/widgets/appointment-calendar';

/** /appointments: the team's calendar by day or week, and the one action that fills it (CRM-05). */
export function AppointmentsPage() {
  const {t} = useTranslation('appointments');
  const {me} = useAuth();
  const [booking, setBooking] = useState(false);
  return (
    <>
      <PageHeader
        title={t('title')}
        description={t('description')}
        actions={
          <Button onClick={() => setBooking(true)}>
            <Plus aria-hidden />
            {t('book')}
          </Button>
        }
      />
      <AppointmentCalendar
        userId={me?.user.id}
        timeZone={me?.dealership.timezone}
      />
      <BookAppointmentDialog open={booking} onOpenChange={setBooking} />
    </>
  );
}
