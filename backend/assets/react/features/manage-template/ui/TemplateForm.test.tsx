import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {ApiError} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {TemplateForm} from './TemplateForm';

const createTemplate = vi.hoisted(() => vi.fn());
const updateTemplate = vi.hoisted(() => vi.fn());
vi.mock('@/entities/template', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/template')>()),
  createTemplate,
  updateTemplate,
}));

const saved = {
  id: 't1',
  name: 'Hello',
  channel: 'text' as const,
  body: 'Hi',
  active: true,
  subject: null,
  created_at: '2026-10-18T10:00:00+00:00',
  updated_at: '2026-10-18T10:00:00+00:00',
};

function renderForm(template?: Parameters<typeof TemplateForm>[0]['template']) {
  const onSaved = vi.fn();
  const onCancel = vi.fn();
  renderWithProviders(
    <TemplateForm template={template} onSaved={onSaved} onCancel={onCancel} />,
  );
  return {onSaved, onCancel};
}

const save = () => screen.getByRole('button', {name: 'Save template'});
const body = () => screen.getByRole('textbox', {name: 'Message'});

describe('TemplateForm', () => {
  beforeEach(() => {
    createTemplate.mockReset();
    updateTemplate.mockReset();
  });

  it('keeps Save disabled until there is a name and a message', async () => {
    renderForm();
    expect(save()).toBeDisabled();
    await userEvent.type(screen.getByLabelText('Name'), 'Hello');
    expect(save()).toBeDisabled();
    await userEvent.type(body(), 'Hi there');
    await waitFor(() => expect(save()).toBeEnabled());
  });

  it('asks for a subject only on an email template', async () => {
    renderForm();
    expect(screen.queryByLabelText('Subject')).not.toBeInTheDocument();
    await userEvent.click(screen.getByRole('combobox', {name: 'Channel'}));
    await userEvent.click(await screen.findByRole('option', {name: 'Email'}));
    expect(screen.getByLabelText('Subject')).toBeInTheDocument();

    await userEvent.type(screen.getByLabelText('Name'), 'Mail');
    await userEvent.type(body(), 'Hi there');
    expect(save()).toBeDisabled();
    await userEvent.type(screen.getByLabelText('Subject'), 'Your visit');
    await waitFor(() => expect(save()).toBeEnabled());
  });

  it('inserts a placeholder into the message from the helper', async () => {
    renderForm();
    await userEvent.type(body(), 'Hi ');
    await userEvent.click(
      screen.getByRole('button', {name: 'Insert {first_name}'}),
    );
    expect(body()).toHaveValue('Hi {first_name}');
  });

  it('previews the message with the placeholders filled in', async () => {
    renderForm();
    await userEvent.type(body(), 'Hi {{first_name}, I am {{rep_name}.');
    expect(screen.getByRole('region', {name: 'Preview'})).toHaveTextContent(
      'Hi Maria, I am Sam.',
    );
  });

  it('names an unknown placeholder and will not save it', async () => {
    renderForm();
    await userEvent.type(screen.getByLabelText('Name'), 'Hello');
    await userEvent.type(body(), 'Hi {{pet_name}');
    expect(await screen.findByText(/Unknown placeholder/)).toHaveTextContent(
      '{pet_name}',
    );
    expect(save()).toBeDisabled();
  });

  it('creates a template and hands it back', async () => {
    createTemplate.mockResolvedValue(saved);
    const {onSaved} = renderForm();
    await userEvent.type(screen.getByLabelText('Name'), 'Hello');
    await userEvent.type(body(), 'Hi');
    await waitFor(() => expect(save()).toBeEnabled());
    await userEvent.click(save());
    await waitFor(() => expect(onSaved).toHaveBeenCalledWith(saved));
    expect(createTemplate).toHaveBeenCalledWith({
      name: 'Hello',
      channel: 'text',
      subject: null,
      body: 'Hi',
      active: true,
    });
  });

  it('edits an existing template: the channel is fixed and only the form values are sent', async () => {
    updateTemplate.mockResolvedValue(saved);
    const {onSaved} = renderForm({...saved, name: 'Old', body: 'Old body'});
    expect(screen.getByRole('combobox', {name: 'Channel'})).toBeDisabled();
    await userEvent.clear(screen.getByLabelText('Name'));
    await userEvent.type(screen.getByLabelText('Name'), 'New');
    await userEvent.click(save());
    await waitFor(() => expect(onSaved).toHaveBeenCalled());
    expect(updateTemplate).toHaveBeenCalledWith('t1', {
      name: 'New',
      subject: null,
      body: 'Old body',
      active: true,
    });
  });

  it('shows the API refusal next to the message and keeps the form open', async () => {
    createTemplate.mockRejectedValue(
      new ApiError(422, 'unknown_placeholder', 'Unknown', [], {
        field: 'body',
        placeholders: ['vin'],
      }),
    );
    const {onSaved} = renderForm();
    await userEvent.type(screen.getByLabelText('Name'), 'Hello');
    await userEvent.type(body(), 'Hi');
    await waitFor(() => expect(save()).toBeEnabled());
    await userEvent.click(save());
    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Unknown placeholder: {vin}',
    );
    expect(onSaved).not.toHaveBeenCalled();
  });
});
