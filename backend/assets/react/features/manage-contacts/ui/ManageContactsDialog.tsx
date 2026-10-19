import {useQueryClient} from '@tanstack/react-query';
import {Mail, Pencil, Phone, Plus, Trash2} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {customersKeys, removeContact, type Customer} from '@/entities/customer';
import {formatPhone} from '@/shared/lib/phone';
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/shared/ui/alert-dialog';
import {Badge} from '@/shared/ui/badge';
import {Button} from '@/shared/ui/button';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/shared/ui/dialog';
import {errorText} from '../lib/errors';
import {ContactForm} from './ContactForm';

type Contact = Customer['contacts'][number];
type Mode = {kind: 'add'} | {kind: 'edit'; contact: Contact} | null;

interface Props {
  customer: Customer;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

function display(contact: Contact): string {
  return contact.kind === 'phone' ? formatPhone(contact.value) : contact.value;
}

/** Every phone number and email of a customer: add, edit, make primary, remove. */
export function ManageContactsDialog({customer, open, onOpenChange}: Props) {
  const {t, i18n} = useTranslation('customers');
  const queryClient = useQueryClient();
  const [mode, setMode] = useState<Mode>(null);
  const [removing, setRemoving] = useState<Contact | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function remove(contact: Contact) {
    setError(null);
    try {
      const updated = await removeContact(customer.id, contact.id);
      queryClient.setQueryData(customersKeys.detail(customer.id), updated);
      void queryClient.invalidateQueries({queryKey: ['customers', 'list']});
      toast.success(t('contacts.removed'));
      setRemoving(null);
    } catch (e) {
      setError(errorText(t, (k) => i18n.exists(k), e));
    }
  }

  return (
    <>
      <Dialog
        open={open}
        onOpenChange={(next) => {
          if (!next) setMode(null);
          onOpenChange(next);
        }}
      >
        <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{t('contacts.title')}</DialogTitle>
          </DialogHeader>
          {customer.contacts.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {t('contacts.empty')}
            </p>
          ) : (
            <ul className="divide-y rounded-md border">
              {customer.contacts.map((contact) => (
                <li key={contact.id} className="flex items-center gap-2 p-3">
                  {contact.kind === 'phone' ? (
                    <Phone
                      aria-label={t('panel.phone')}
                      className="size-4 shrink-0 text-muted-foreground"
                    />
                  ) : (
                    <Mail
                      aria-label={t('panel.email')}
                      className="size-4 shrink-0 text-muted-foreground"
                    />
                  )}
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm">{display(contact)}</p>
                    <p className="flex items-center gap-2 text-xs text-muted-foreground">
                      {contact.label && <span>{contact.label}</span>}
                      {contact.is_primary && (
                        <Badge variant="secondary">
                          {t('contacts.primary')}
                        </Badge>
                      )}
                    </p>
                  </div>
                  <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`${t('contacts.edit')} ${display(contact)}`}
                    onClick={() => setMode({kind: 'edit', contact})}
                  >
                    <Pencil />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`${t('contacts.remove')} ${display(contact)}`}
                    onClick={() => setRemoving(contact)}
                  >
                    <Trash2 />
                  </Button>
                </li>
              ))}
            </ul>
          )}
          {mode ? (
            <ContactForm
              key={mode.kind === 'edit' ? mode.contact.id : 'add'}
              customer={customer}
              contact={mode.kind === 'edit' ? mode.contact : undefined}
              onDone={() => setMode(null)}
            />
          ) : (
            <Button variant="outline" onClick={() => setMode({kind: 'add'})}>
              <Plus /> {t('contacts.add')}
            </Button>
          )}
          {error && (
            <p role="alert" className="text-sm text-danger">
              {error}
            </p>
          )}
        </DialogContent>
      </Dialog>
      <AlertDialog
        open={removing !== null}
        onOpenChange={(next) => !next && setRemoving(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {t('contacts.remove_title', {
                value: removing ? display(removing) : '',
              })}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {t('contacts.remove_description', {name: customer.name})}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common:action.cancel')}</AlertDialogCancel>
            <Button
              variant="destructive"
              onClick={() => removing && void remove(removing)}
            >
              {t('contacts.remove')}
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
