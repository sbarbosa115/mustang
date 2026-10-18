import {zodResolver} from '@hookform/resolvers/zod';
import {useEffect, type ReactNode} from 'react';
import {Controller, useForm, type FieldErrors} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import {ApiError} from '@/shared/api';
import {Button} from '@/shared/ui/button';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/ui/select';
import {Textarea} from '@/shared/ui/textarea';
import {
  EMPTY_VALUES,
  toVehicleInput,
  valuesFromVehicle,
  vehicleSchema,
  type Vehicle,
  type VehicleFormValues,
  type VehicleInput,
} from '../model/form';

export interface VehicleFormProps {
  /** Set to edit this vehicle; the stock number is then fixed. */
  vehicle?: Vehicle;
  onSubmit: (input: VehicleInput) => void;
  submitting?: boolean;
  /** What the API refused, shown next to the field it names (or at the top when it names none). */
  error?: ApiError | null;
  onCancel?: () => void;
  submitLabel?: ReactNode;
}

const CONDITIONS = ['new', 'used', 'cpo'] as const;
const TEXT_FIELDS = [
  'trim',
  'vin',
  'body',
  'drivetrain',
  'fuel',
  'exterior_color',
  'interior_color',
] as const;

/**
 * Every field of a vehicle (FR-21). The price is typed in dollars and sent as cents. Save stays disabled until the
 * required fields are valid.
 */
export function VehicleForm({
  vehicle,
  onSubmit,
  submitting = false,
  error,
  onCancel,
  submitLabel,
}: VehicleFormProps) {
  const {t} = useTranslation('inventory');
  const form = useForm<VehicleFormValues>({
    resolver: zodResolver(vehicleSchema),
    mode: 'onChange',
    defaultValues: vehicle ? valuesFromVehicle(vehicle) : EMPTY_VALUES,
  });
  const {errors, isValid} = form.formState;

  // A refusal from the API lands on its field: a taken stock number, or a violation the form did not catch.
  useEffect(() => {
    if (!error) return;
    if (error.code === 'stock_number_taken') {
      form.setError('stock_number', {message: 'stock_number_taken'});
    }
    for (const violation of error.violations) {
      if (violation.field in EMPTY_VALUES) {
        form.setError(violation.field as keyof VehicleFormValues, {
          message: 'server',
          type: 'server',
        });
      }
    }
  }, [error, form]);

  const message = (name: keyof VehicleFormValues) => {
    const key = (errors as FieldErrors<VehicleFormValues>)[name]?.message;
    if (!key) return undefined;
    return key === 'server'
      ? (error?.fieldError(name) ?? t('form.error.generic'))
      : t(`form.error.${key}`);
  };

  const field = (
    name: keyof VehicleFormValues,
    label: string,
    props: React.ComponentProps<typeof Input> = {},
  ) => (
    <div className="space-y-1.5">
      <Label htmlFor={`vehicle-${name}`}>{label}</Label>
      <Input
        id={`vehicle-${name}`}
        aria-invalid={errors[name] ? true : undefined}
        aria-describedby={errors[name] ? `vehicle-${name}-error` : undefined}
        {...form.register(name)}
        {...props}
      />
      {errors[name] && (
        <p
          id={`vehicle-${name}-error`}
          role="alert"
          className="text-sm text-danger"
        >
          {message(name)}
        </p>
      )}
    </div>
  );

  const topError =
    error &&
    error.code !== 'stock_number_taken' &&
    error.violations.length === 0
      ? t(`form.error.${error.code}`, {defaultValue: t('form.error.generic')})
      : null;

  return (
    <form
      noValidate
      className="space-y-4"
      onSubmit={form.handleSubmit((values) => onSubmit(toVehicleInput(values)))}
    >
      {topError && (
        <p role="alert" className="text-sm text-danger">
          {topError}
        </p>
      )}
      <div className="grid gap-4 sm:grid-cols-2">
        {field('stock_number', t('form.stock_number'), {
          readOnly: Boolean(vehicle),
          className: vehicle ? 'bg-muted' : undefined,
          autoFocus: !vehicle,
        })}
        {field('year', t('form.year'), {inputMode: 'numeric', maxLength: 4})}
        {field('make', t('form.make'))}
        {field('model', t('form.model'))}
        <div className="space-y-1.5">
          <Label htmlFor="vehicle-condition">{t('form.condition')}</Label>
          <Controller
            control={form.control}
            name="condition"
            render={({field: f}) => (
              <Select value={f.value} onValueChange={f.onChange}>
                <SelectTrigger
                  id="vehicle-condition"
                  className="h-11 w-full sm:h-9"
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {CONDITIONS.map((c) => (
                    <SelectItem key={c} value={c}>
                      {t(`condition.${c}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        </div>
        {field('price', t('form.price'), {
          inputMode: 'decimal',
          placeholder: t('form.price_placeholder'),
        })}
        {field('mileage', t('form.mileage'), {inputMode: 'numeric'})}
        {TEXT_FIELDS.map((name) => (
          <div key={name}>{field(name, t(`form.${name}`))}</div>
        ))}
      </div>
      {field('photo_url', t('form.photo_url'), {
        type: 'url',
        inputMode: 'url',
        placeholder: 'https://',
      })}
      <div className="space-y-1.5">
        <Label htmlFor="vehicle-description">{t('form.description')}</Label>
        <Textarea
          id="vehicle-description"
          rows={3}
          {...form.register('description')}
        />
      </div>
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        {onCancel && (
          <Button type="button" variant="outline" onClick={onCancel}>
            {t('form.cancel')}
          </Button>
        )}
        <Button type="submit" disabled={!isValid || submitting}>
          {submitLabel ?? t('form.save')}
        </Button>
      </div>
    </form>
  );
}
