import {zodResolver} from '@hookform/resolvers/zod';
import {useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {Controller, useForm} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {z} from 'zod';
import {updateUser, usersKeys, type Role} from '@/entities/user';
import type {Schema} from '@/shared/api';
import {ApiError} from '@/shared/api';
import {Button} from '@/shared/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/shared/ui/dialog';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/ui/select';
import {errorText} from '../lib/errors';

const ROLES: Role[] = ['sales', 'bdc', 'manager', 'admin'];

const schema = z.object({
  first_name: z.string().trim().min(1).max(80),
  last_name: z.string().trim().min(1).max(80),
  role: z.enum(['sales', 'bdc', 'manager', 'admin']),
});
type Values = z.infer<typeof schema>;

interface Props {
  user: Schema<'UserOutput'>;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/** Change a user's name or role. The last active admin cannot be demoted: the API answers `last_admin`. */
export function EditUserDialog({user, open, onOpenChange}: Props) {
  const {t, i18n} = useTranslation('users');
  const queryClient = useQueryClient();
  const [formError, setFormError] = useState<string | null>(null);
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      first_name: user.first_name,
      last_name: user.last_name,
      role: user.role,
    },
  });
  const {errors, isValid, isSubmitting, isDirty} = form.formState;

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null);
    try {
      const saved = await updateUser(user.id, {
        first_name: values.first_name.trim(),
        last_name: values.last_name.trim(),
        role: values.role,
      });
      void queryClient.invalidateQueries({queryKey: usersKeys.all});
      toast.success(t('edit.saved', {name: saved.name}));
      onOpenChange(false);
    } catch (e) {
      if (e instanceof ApiError && e.violations.length > 0) {
        for (const v of e.violations) {
          form.setError(v.field as keyof Values, {message: v.message});
        }
      } else {
        setFormError(errorText(t, (k) => i18n.exists(k), e));
      }
    }
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('edit.title', {name: user.name})}</DialogTitle>
          <DialogDescription>{t('edit.description')}</DialogDescription>
        </DialogHeader>
        <form onSubmit={onSubmit} className="space-y-4" noValidate>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="edit_first_name">{t('edit.first_name')}</Label>
              <Input id="edit_first_name" {...form.register('first_name')} />
              {errors.first_name?.message && (
                <p role="alert" className="text-sm text-danger">
                  {errors.first_name.message}
                </p>
              )}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="edit_last_name">{t('edit.last_name')}</Label>
              <Input id="edit_last_name" {...form.register('last_name')} />
              {errors.last_name?.message && (
                <p role="alert" className="text-sm text-danger">
                  {errors.last_name.message}
                </p>
              )}
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="edit_role">{t('edit.role')}</Label>
            <Controller
              control={form.control}
              name="role"
              render={({field}) => (
                <Select value={field.value} onValueChange={field.onChange}>
                  <SelectTrigger id="edit_role" className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {ROLES.map((role) => (
                      <SelectItem key={role} value={role}>
                        {t(`settings:role.${role}`)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            />
          </div>
          {formError && (
            <p role="alert" className="text-sm text-danger">
              {formError}
            </p>
          )}
          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
            >
              {t('common:action.cancel')}
            </Button>
            <Button
              type="submit"
              disabled={!isValid || !isDirty || isSubmitting}
            >
              {isSubmitting ? t('edit.submitting') : t('edit.submit')}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
