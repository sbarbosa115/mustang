// Generates assets/types/api.d.ts from the OpenAPI dump. Output DTOs always serialize every property (null
// included), so their properties are marked required before generating; inputs keep their optional fields.
import {readFileSync, writeFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';

const source = 'assets/types/openapi.json';
const normalized = 'var/openapi.normalized.json';
const doc = JSON.parse(readFileSync(source, 'utf8'));
for (const [name, schema] of Object.entries(doc.components?.schemas ?? {})) {
  if (/Output$/.test(name) && schema.properties) {
    schema.required = Object.keys(schema.properties);
  }
}
writeFileSync(normalized, JSON.stringify(doc));
execFileSync(
  'npx',
  ['openapi-typescript', normalized, '-o', 'assets/types/api.d.ts'],
  {stdio: 'inherit'},
);
