import type {components, paths} from '../../../types/api';

/** A response or request body type of the API, e.g. `Schema<'CustomerOutput'>`. */
export type Schema<K extends keyof components['schemas']> =
  components['schemas'][K];

export type {paths};
