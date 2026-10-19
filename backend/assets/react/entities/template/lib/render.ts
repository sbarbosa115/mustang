/** The placeholders a template may use; anything else is refused by the API (item "templates"). */
export const PLACEHOLDERS = [
  'first_name',
  'last_name',
  'rep_name',
  'dealership',
  'vehicle',
  'appointment_time',
] as const;

export type Placeholder = (typeof PLACEHOLDERS)[number];
export type TemplateValues = Partial<
  Record<Placeholder, string | null | undefined>
>;

/**
 * Fills {first_name}-style placeholders. A placeholder with no value is left as written, so the rep sees it and
 * fills it in before sending instead of sending an empty gap.
 */
export function renderTemplate(body: string, values: TemplateValues): string {
  return body.replace(/\{([a-z_]+)\}/g, (match, name: string) => {
    const value = values[name as Placeholder];
    return value == null || value === '' ? match : value;
  });
}

/** The placeholders in a body that are not known ones. */
export function unknownPlaceholders(body: string): string[] {
  const found = [...body.matchAll(/\{([a-z_]+)\}/g)].map((m) => m[1] ?? '');
  return [
    ...new Set(
      found.filter(
        (name) => !(PLACEHOLDERS as readonly string[]).includes(name),
      ),
    ),
  ];
}
