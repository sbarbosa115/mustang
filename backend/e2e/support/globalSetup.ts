import {request} from '@playwright/test';

/**
 * Before any spec: the stack answers and was prepared for a smoke run (e2e/prepare.sh): the fake SMS transport, so
 * no spec ever reaches a real carrier, and the demo seed.
 */
export default async function globalSetup(): Promise<void> {
  const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:8080';
  const api = await request.newContext({baseURL});
  const health = await api.get('/api/v1/health');
  if (!health.ok()) {
    throw new Error(
      `The stack at ${baseURL} does not answer (GET /api/v1/health: ${health.status()}). Run e2e/smoke.sh.`,
    );
  }
  const {sms_transport: transport} = (await health.json()) as {
    sms_transport: string;
  };
  if (transport !== 'fake') {
    throw new Error(
      `The stack uses the "${transport}" SMS transport. Run e2e/smoke.sh, which switches it to "fake".`,
    );
  }
  const login = await api.post('/api/v1/auth/login', {
    data: {email: 'admin@mustang.test', password: 'Mustang-dev-1'},
    headers: {
      'Origin': new URL(baseURL).origin,
      'X-Forwarded-For': '10.99.0.1',
    },
  });
  if (!login.ok()) {
    throw new Error(
      'The demo seed is missing (admin@mustang.test cannot sign in). Run e2e/smoke.sh, which seeds it.',
    );
  }
  await api.dispose();
}
