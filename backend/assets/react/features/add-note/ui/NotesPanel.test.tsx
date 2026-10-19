import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {NotesPanel} from './NotesPanel';

const listNotes = vi.hoisted(() => vi.fn());
const addNote = vi.hoisted(() => vi.fn());
vi.mock('@/entities/customer', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/customer')>()),
  listNotes,
  addNote,
}));

const note = (id: string, body: string) => ({
  id,
  body,
  created_at: '2026-10-18T12:00:00+00:00',
  author: {id: 'u1', name: 'Sam Patel'},
});

describe('NotesPanel', () => {
  beforeEach(() => {
    listNotes.mockReset();
    addNote.mockReset();
  });

  it('says there are no notes yet', async () => {
    listNotes.mockResolvedValue([]);
    renderWithProviders(<NotesPanel customerId="c1" />);
    expect(await screen.findByText('No notes yet.')).toBeVisible();
  });

  it('lists notes with their author', async () => {
    listNotes.mockResolvedValue([note('n1', 'Wants a third row.')]);
    renderWithProviders(<NotesPanel customerId="c1" />);
    expect(await screen.findByText('Wants a third row.')).toBeVisible();
    expect(screen.getByText('Sam Patel')).toBeVisible();
  });

  it('keeps Add note disabled until there is text, then adds the trimmed note and clears the box', async () => {
    listNotes.mockResolvedValue([]);
    addNote.mockResolvedValue(note('n2', 'Call after 5 pm.'));
    renderWithProviders(<NotesPanel customerId="c1" />);
    await screen.findByText('No notes yet.');
    const add = screen.getByRole('button', {name: 'Add note'});
    expect(add).toBeDisabled();

    await userEvent.type(
      screen.getByLabelText('New note'),
      '  Call after 5 pm. ',
    );
    await userEvent.click(add);

    await waitFor(() =>
      expect(addNote).toHaveBeenCalledWith('c1', {body: 'Call after 5 pm.'}),
    );
    await waitFor(() =>
      expect(screen.getByLabelText('New note')).toHaveValue(''),
    );
  });

  it('keeps what was typed for the next note while the previous one is still being saved', async () => {
    listNotes.mockResolvedValue([]);
    let saved: (value: ReturnType<typeof note>) => void = () => undefined;
    addNote.mockReturnValue(
      new Promise((resolve) => {
        saved = resolve;
      }),
    );
    renderWithProviders(<NotesPanel customerId="c1" />);
    await screen.findByText('No notes yet.');
    const box = screen.getByLabelText('New note');

    await userEvent.type(box, 'First.');
    await userEvent.click(screen.getByRole('button', {name: 'Add note'}));
    await userEvent.clear(box);
    await userEvent.type(box, 'Second.');
    saved(note('n1', 'First.'));

    await waitFor(() => expect(listNotes).toHaveBeenCalledTimes(2));
    expect(box).toHaveValue('Second.');
  });

  it('marks the customer timeline stale, so the new note shows there without a reload', async () => {
    listNotes.mockResolvedValue([]);
    addNote.mockResolvedValue(note('n2', 'Call after 5 pm.'));
    const {queryClient} = renderWithProviders(<NotesPanel customerId="c1" />);
    await screen.findByText('No notes yet.');
    queryClient.setQueryData(['timeline', 'list', {customerId: 'c1'}], {
      items: [],
      total: 0,
    });

    await userEvent.type(screen.getByLabelText('New note'), 'Call after 5 pm.');
    await userEvent.click(screen.getByRole('button', {name: 'Add note'}));

    await waitFor(() =>
      expect(
        queryClient.getQueryState(['timeline', 'list', {customerId: 'c1'}])
          ?.isInvalidated,
        'the timeline cached while it was empty would stay empty for 15 seconds',
      ).toBe(true),
    );
  });

  it('offers a retry when the notes cannot be loaded', async () => {
    listNotes.mockImplementationOnce(() => Promise.reject(new Error('down')));
    listNotes.mockResolvedValue([note('n1', 'Back again.')]);
    renderWithProviders(<NotesPanel customerId="c1" />);
    await userEvent.click(
      await screen.findByRole('button', {name: 'Try again'}),
    );
    expect(await screen.findByText('Back again.')).toBeVisible();
  });
});
