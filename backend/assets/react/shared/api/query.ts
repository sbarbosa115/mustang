import type {paths} from './schema';

/** The query-string parameters of a GET endpoint, from the schema. */
export type Query<P extends keyof paths> = paths[P] extends {
  get: {parameters: {query?: infer Q}};
}
  ? NonNullable<Q>
  : never;
