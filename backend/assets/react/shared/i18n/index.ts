import i18next, {type i18n as I18n} from 'i18next';
import {initReactI18next} from 'react-i18next';

/** Namespaces, one per slice area; each item owns its file (docs/pdr/prd-mvp.md, "i18n namespaces"). */
export const NAMESPACES = [
  'common',
  'shell',
  'auth',
  'settings',
  'customers',
  'inventory',
  'leads',
  'deals',
  'consent',
  'templates',
  'appointments',
  'inbox',
  'users',
  'imports',
  'timeline',
  'today',
  'team',
  'reports',
  'audit',
] as const;

const files = import.meta.glob<{default: Record<string, unknown>}>(
  './locales/*/*.json',
  {eager: true},
);

function resources(): Record<string, Record<string, Record<string, unknown>>> {
  const all: Record<string, Record<string, Record<string, unknown>>> = {};
  for (const [path, module] of Object.entries(files)) {
    const match = /\.\/locales\/([^/]+)\/([^/]+)\.json$/.exec(path);
    if (!match) continue;
    const [, language, namespace] = match as unknown as [
      string,
      string,
      string,
    ];
    all[language] ??= {};
    all[language][namespace] = module.default;
  }
  return all;
}

/**
 * English only for now; Spanish is added by adding locales/es/*.json. Strings are never concatenated: use
 * interpolation ({{name}}) and plurals (_one/_other).
 */
export function createI18n(options: {strict?: boolean} = {}): I18n {
  const instance = i18next.createInstance();
  void instance.use(initReactI18next).init({
    lng: 'en',
    fallbackLng: 'en',
    ns: [...NAMESPACES],
    defaultNS: 'common',
    resources: resources(),
    interpolation: {escapeValue: false},
    returnNull: false,
    initAsync: false,
    // In tests a missing key fails loudly instead of rendering the key.
    ...(options.strict
      ? {
          saveMissing: true,
          missingKeyHandler: (
            _lngs: readonly string[],
            ns: string,
            key: string,
          ) => {
            throw new Error(`Missing translation ${ns}:${key}`);
          },
        }
      : {}),
  });
  return instance;
}
