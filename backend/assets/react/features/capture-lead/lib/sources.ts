import type {Schema} from '@/shared/api';

export type LeadSource = Schema<'LeadSource'>;

/** The sources a person captures by hand; "import" comes from the spreadsheet import only. */
export const CAPTURE_SOURCES: LeadSource[] = [
  'phone',
  'walk_in',
  'website',
  'manual',
  'other',
];
