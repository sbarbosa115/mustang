import type {Schema} from '@/shared/api';

export type ConsentList = Schema<'ConsentListOutput'>;
export type Consent = Schema<'ConsentOutput'>;
export type SuppressionPage = Schema<'SuppressionPageOutput'>;
export type RecordConsentInput = Schema<'RecordConsentInput'>;
export type RevokeConsentInput = Schema<'RevokeConsentInput'>;
