import {useMutation, useQueryClient} from '@tanstack/react-query';
import {Monitor, Moon, Sun} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {updatePreferences, usersKeys} from '@/entities/user';
import {useTheme, type ThemePreference} from '@/shared/lib/theme';
import {RadioGroup, RadioGroupItem} from '@/shared/ui/radio-group';
import {Label} from '@/shared/ui/label';

const OPTIONS: Array<{value: ThemePreference; Icon: typeof Sun}> = [
  {value: 'light', Icon: Sun},
  {value: 'dark', Icon: Moon},
  {value: 'system', Icon: Monitor},
];

/**
 * Light, Dark or System. Applies at once, then saves on the server so every device follows (DESIGN.md "Themes").
 */
export function ThemeSwitch({onSaved}: {onSaved?: () => void}) {
  const {t} = useTranslation('shell');
  const {preference, setPreference} = useTheme();
  const queryClient = useQueryClient();
  const save = useMutation({
    mutationFn: (theme: ThemePreference) => updatePreferences({theme}),
    onSuccess: (me) => {
      queryClient.setQueryData([...usersKeys.all, 'me'], me);
      onSaved?.();
    },
  });

  return (
    <RadioGroup
      value={preference}
      onValueChange={(value) => {
        const next = value as ThemePreference;
        setPreference(next);
        save.mutate(next);
      }}
      aria-label={t('account.theme')}
      className="grid grid-cols-3 gap-2"
    >
      {OPTIONS.map(({value, Icon}) => (
        <Label
          key={value}
          htmlFor={`theme-${value}`}
          className="flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-md border px-3 py-2 has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-accent"
        >
          <RadioGroupItem
            id={`theme-${value}`}
            value={value}
            className="sr-only"
          />
          <Icon aria-hidden className="size-4" />
          {t(`theme.${value}`)}
        </Label>
      ))}
    </RadioGroup>
  );
}
