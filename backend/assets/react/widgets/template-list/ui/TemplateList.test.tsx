import {screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {TemplateList} from './TemplateList';

const listTemplates = vi.hoisted(() => vi.fn());
const deleteTemplate = vi.hoisted(() => vi.fn());
vi.mock('@/entities/template', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/template')>()),
  listTemplates,
  deleteTemplate,
}));

const row = (over: object) => ({
  id: 't1',
  name: 'First reply',
  channel: 'text',
  body: 'Hi {first_name}',
  subject: null,
  active: true,
  created_at: '2026-10-18T10:00:00+00:00',
  updated_at: '2026-10-18T10:00:00+00:00',
  ...over,
});

describe('TemplateList', () => {
  beforeEach(() => {
    listTemplates.mockReset();
    deleteTemplate.mockReset();
  });

  it('shows a skeleton while loading', () => {
    listTemplates.mockReturnValue(new Promise(() => undefined));
    renderWithProviders(<TemplateList canManage />);
    expect(screen.getByLabelText('Loading')).toHaveAttribute(
      'aria-busy',
      'true',
    );
    expect(screen.queryByText('First reply')).not.toBeInTheDocument();
  });

  it('says so when there are no templates and offers the action', async () => {
    listTemplates.mockResolvedValue({items: []});
    renderWithProviders(
      <TemplateList canManage emptyAction={<button>Make one</button>} />,
    );
    expect(await screen.findByText(/No templates yet/)).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Make one'})).toBeInTheDocument();
  });

  it('offers a retry when the list cannot be loaded', async () => {
    listTemplates.mockRejectedValue(new Error('boom'));
    renderWithProviders(<TemplateList canManage />);
    expect(
      await screen.findByText('This list could not be loaded.'),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Try again'})).toBeInTheDocument();
  });

  it('lists a template with its channel and status in words', async () => {
    listTemplates.mockResolvedValue({
      items: [
        row({}),
        row({id: 't2', name: 'Quote', channel: 'email', active: false}),
      ],
    });
    renderWithProviders(<TemplateList canManage />);
    const table = await screen.findByRole('table');
    expect(within(table).getByText('First reply')).toBeInTheDocument();
    expect(within(table).getByText('Email')).toBeInTheDocument();
    expect(within(table).getByText('Inactive')).toBeInTheDocument();
  });

  it('hides edit and delete from people who cannot manage templates', async () => {
    listTemplates.mockResolvedValue({items: [row({})]});
    renderWithProviders(<TemplateList canManage={false} />);
    await screen.findByRole('table');
    expect(
      screen.queryByRole('button', {name: 'Delete First reply'}),
    ).not.toBeInTheDocument();
  });

  it('confirms a delete in a dialog that names the template', async () => {
    listTemplates.mockResolvedValue({items: [row({})]});
    deleteTemplate.mockResolvedValue(undefined);
    renderWithProviders(<TemplateList canManage />);
    // The table and the phone cards are both in the DOM; either one opens the same dialog.
    const buttons = await screen.findAllByRole('button', {
      name: 'Delete First reply',
    });
    await userEvent.click(buttons[0]!);
    const dialog = await screen.findByRole('alertdialog');
    expect(within(dialog).getByText('Delete First reply?')).toBeInTheDocument();
    expect(deleteTemplate).not.toHaveBeenCalled();
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Delete template'}),
    );
    await waitFor(() => expect(deleteTemplate).toHaveBeenCalledWith('t1'));
  });
});
