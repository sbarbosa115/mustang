import {screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import type {ImportJob} from '@/entities/import-job';
import {renderWithProviders} from '@/shared/test/render';
import {ImportStatus} from './ImportStatus';

const listImports = vi.hoisted(() => vi.fn());
vi.mock('@/entities/import-job', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/import-job')>()),
  listImports,
}));

function job(overrides: Partial<ImportJob> = {}): ImportJob {
  return {
    id: 'j1',
    type: 'customers',
    file_name: 'old-crm.csv',
    status: 'done',
    rows_total: 6,
    rows_created: 3,
    rows_updated: 1,
    rows_skipped: 0,
    rows_failed: 2,
    errors: [
      {row: 3, message: '"12345" is not a valid phone number.', field: 'phone'},
      {row: 5, message: 'The row needs a phone or an email.', field: 'phone'},
    ],
    created_at: new Date().toISOString(),
    uploaded_by: {id: 'u1', name: 'Morgan Manager'},
    finished_at: new Date().toISOString(),
    ...overrides,
  };
}

const page = (items: ImportJob[]) => ({
  items,
  total: items.length,
  page: 1,
  per_page: 10,
});

function rowOf(text: string): ReturnType<typeof within> {
  const cell = screen
    .getAllByText(text)
    .find((el) => el.closest('tr') !== null);
  return within(cell?.closest('tr') as HTMLElement);
}

describe('ImportStatus', () => {
  beforeEach(() => {
    listImports.mockReset();
  });

  it('shows skeletons while loading', () => {
    listImports.mockReturnValue(new Promise(() => {}));
    renderWithProviders(<ImportStatus />);
    expect(screen.getByLabelText('Loading')).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('says there are no imports yet', async () => {
    listImports.mockResolvedValue(page([]));
    renderWithProviders(<ImportStatus />);
    expect(
      await screen.findByText(
        'No imports yet. Download a sample file to see the columns, then upload your own.',
      ),
    ).toBeVisible();
  });

  it('offers a retry when the list cannot load', async () => {
    listImports.mockRejectedValue(new Error('down'));
    renderWithProviders(<ImportStatus />);
    expect(
      await screen.findByRole('button', {name: 'Try again'}),
    ).toBeVisible();
  });

  it('shows a finished import with its counts, and its row errors on demand', async () => {
    listImports.mockResolvedValue(page([job()]));
    renderWithProviders(<ImportStatus />);

    await screen.findAllByText('old-crm.csv');
    const row = rowOf('old-crm.csv');
    expect(row.getByText('Done')).toBeVisible();
    expect(row.getByText('Customers')).toBeVisible();
    expect(row.getByText('by Morgan Manager')).toBeVisible();
    expect(
      row.getByText('3 created, 1 updated, 0 unchanged, 2 failed'),
    ).toBeVisible();
    expect(row.queryByText('Row 3:')).not.toBeInTheDocument();

    await userEvent.click(row.getByRole('button', {name: 'Show 2 problems'}));
    expect(row.getByText('Row 3:')).toBeVisible();
    expect(row.getByText('"12345" is not a valid phone number.')).toBeVisible();
    expect(row.getAllByText('(column phone)')).toHaveLength(2);
    expect(row.getByRole('button', {name: 'Hide problems'})).toHaveAttribute(
      'aria-expanded',
      'true',
    );
  });

  it('says a queued import is waiting, and a failed one imported nothing', async () => {
    listImports.mockResolvedValue(
      page([
        job({
          id: 'j2',
          file_name: 'leads.csv',
          type: 'leads',
          status: 'pending',
          errors: [],
        }),
        job({
          id: 'j3',
          file_name: 'broken.csv',
          status: 'failed',
          errors: [
            {
              row: 1,
              message: 'The file has no last_name column.',
              field: 'last_name',
            },
          ],
        }),
      ]),
    );
    renderWithProviders(<ImportStatus />);

    await screen.findAllByText('leads.csv');
    const queued = rowOf('leads.csv');
    expect(queued.getByText('Queued')).toBeVisible();
    expect(queued.getByText('Open leads')).toBeVisible();
    expect(queued.getByText('Waiting for the worker…')).toBeVisible();
    const failed = rowOf('broken.csv');
    expect(failed.getByText('Failed')).toBeVisible();
    expect(failed.getByText('Nothing was imported')).toBeVisible();
    await userEvent.click(
      failed.getByRole('button', {name: 'Show the problem'}),
    );
    expect(failed.getByText('The file has no last_name column.')).toBeVisible();
  });

  it('follows a running import until the worker is done', async () => {
    listImports
      .mockResolvedValueOnce(page([job({status: 'processing', errors: []})]))
      .mockResolvedValue(page([job({errors: []})]));
    renderWithProviders(<ImportStatus />);

    expect((await screen.findAllByText('Importing')).length).toBeGreaterThan(0);
    expect(
      (await screen.findAllByText('Done', {}, {timeout: 4000})).length,
    ).toBeGreaterThan(0);
    expect(listImports).toHaveBeenCalledTimes(2);
  });
});
