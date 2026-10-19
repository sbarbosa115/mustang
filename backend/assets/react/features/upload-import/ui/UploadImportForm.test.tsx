import {screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {ApiError} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {UploadImportForm} from './UploadImportForm';

const startImport = vi.hoisted(() => vi.fn());
vi.mock('@/entities/import-job', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/import-job')>()),
  startImport,
}));

const csv = () =>
  new File(['first_name,last_name,phone,email\n'], 'old.csv', {
    type: 'text/csv',
  });

describe('UploadImportForm', () => {
  beforeEach(() => {
    startImport.mockReset();
  });

  it('keeps Upload disabled until a file is chosen, then uploads it as the chosen type', async () => {
    startImport.mockResolvedValue({id: 'j1', file_name: 'old.csv'});
    renderWithProviders(<UploadImportForm />);

    const submit = screen.getByRole('button', {name: 'Upload and import'});
    expect(submit).toBeDisabled();
    await userEvent.click(screen.getByRole('radio', {name: 'Open leads'}));
    await userEvent.upload(screen.getByLabelText('CSV file'), csv());
    await userEvent.click(submit);

    expect(startImport).toHaveBeenCalledWith('leads', expect.any(File));
    expect(
      screen.getByRole('button', {name: 'Upload and import'}),
    ).toBeDisabled();
  });

  it('links the sample file of the chosen type and explains its columns', async () => {
    renderWithProviders(<UploadImportForm />);

    expect(
      screen.getByRole('link', {name: 'Download the sample customers.csv'}),
    ).toHaveAttribute('href', '/api/v1/imports/template/customers.csv');
    expect(
      screen.getByText(/sms_consent or email_consent records consent/),
    ).toBeVisible();
    await userEvent.click(screen.getByRole('radio', {name: 'Open leads'}));
    expect(
      screen.getByRole('link', {name: 'Download the sample leads.csv'}),
    ).toHaveAttribute('href', '/api/v1/imports/template/leads.csv');
    expect(screen.getByText(/goes to round robin/)).toBeVisible();
  });

  it('shows the refusal by its code', async () => {
    startImport.mockRejectedValue(
      new ApiError(413, 'file_too_large', 'Too large'),
    );
    renderWithProviders(<UploadImportForm />);

    await userEvent.upload(screen.getByLabelText('CSV file'), csv());
    await userEvent.click(
      screen.getByRole('button', {name: 'Upload and import'}),
    );

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'The file is too large (10 MB at most).',
    );
  });

  it('falls back to a generic message for an unexpected error', async () => {
    startImport.mockRejectedValue(new Error('network'));
    renderWithProviders(<UploadImportForm />);

    await userEvent.upload(screen.getByLabelText('CSV file'), csv());
    await userEvent.click(
      screen.getByRole('button', {name: 'Upload and import'}),
    );

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'The file could not be uploaded. Try again.',
    );
  });
});
