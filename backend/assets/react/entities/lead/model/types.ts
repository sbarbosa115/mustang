import type {Schema} from '@/shared/api';

export type Lead = Schema<'LeadOutput'>;
export type LeadPage = Schema<'LeadPageOutput'>;
export type Routing = Schema<'RoutingOutput'>;
export type CaptureLeadInput = Schema<'CaptureLeadInput'>;
export type CloseLeadInput = Schema<'CloseLeadInput'>;
export type LeadAssigneeInput = Schema<'LeadAssigneeInput'>;
export type RoutingInput = Schema<'RoutingInput'>;
