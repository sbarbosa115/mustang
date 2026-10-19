export type Channel = 'text' | 'email' | 'call';
export type ConsentState = 'granted' | 'revoked' | 'missing' | 'opted_out';
export type ChannelAvailability = Record<Channel, ConsentState>;
