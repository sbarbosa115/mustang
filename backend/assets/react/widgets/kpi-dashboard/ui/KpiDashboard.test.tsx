import {screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {KpiDashboard} from './KpiDashboard';

const fetchKpis = vi.hoisted(() => vi.fn());
vi.mock('@/entities/kpi', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/kpi')>()),
  fetchKpis,
}));

const report = {
  period: {from: '2026-09-19', to: '2026-10-18'},
  previous: {from: '2026-08-20', to: '2026-09-18'},
  kpis: [
    {
      code: 'first_response_minutes',
      unit: 'minutes',
      value: 12,
      previous_value: 20,
    },
    {code: 'leads_followed', unit: 'percent', value: 64.5, previous_value: 50},
    {code: 'lead_to_contact', unit: 'percent', value: null, previous_value: 40},
    {
      code: 'contact_to_appointment',
      unit: 'percent',
      value: 30,
      previous_value: null,
    },
    {
      code: 'appointment_to_show',
      unit: 'percent',
      value: 70,
      previous_value: 70,
    },
    {code: 'show_to_sale', unit: 'percent', value: 25, previous_value: 30},
    {
      code: 'units_per_salesperson',
      unit: 'units',
      value: 3.5,
      previous_value: 2,
    },
  ],
  funnel: [
    {code: 'leads', count: 40},
    {code: 'contacted', count: 20},
    {code: 'appointments', count: 10},
    {code: 'shows', count: 7},
    {code: 'sales', count: 2},
  ],
};

function tile(label: string) {
  const el = screen.getAllByText(label)[0]?.closest('[data-slot="card"]');
  expect(el).not.toBeNull();
  return within(el as HTMLElement);
}

describe('KpiDashboard', () => {
  beforeEach(() => {
    fetchKpis.mockReset();
  });

  it('shows skeletons while loading', () => {
    fetchKpis.mockReturnValue(new Promise(() => {}));
    renderWithProviders(<KpiDashboard />);
    expect(screen.getByLabelText('Loading')).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('asks for the period it is given', async () => {
    fetchKpis.mockResolvedValue(report);
    renderWithProviders(<KpiDashboard from="2026-09-19" to="2026-10-18" />);
    await screen.findByText('From lead to sale');
    expect(fetchKpis).toHaveBeenCalledWith({
      from: '2026-09-19',
      to: '2026-10-18',
    });
    expect(screen.getByText(/2026-09-19 to 2026-10-18/)).toBeVisible();
  });

  it('shows each KPI with its change, and "No data" for a missing rate', async () => {
    fetchKpis.mockResolvedValue(report);
    renderWithProviders(<KpiDashboard />);
    await screen.findByText('From lead to sale');

    expect(tile('First response time').getByText('12 min')).toBeVisible();
    expect(
      tile('Leads followed up in 24 hours').getByText('64.5%'),
    ).toBeVisible();
    expect(
      tile('Leads followed up in 24 hours').getByText('+14.5 pts, was 50%'),
    ).toBeVisible();
    expect(tile('Units per salesperson').getByText('3.5')).toBeVisible();
    expect(tile('Lead to contact').getByText('No data')).toBeVisible();
    expect(tile('Lead to contact').getByText('No previous data')).toBeVisible();
    expect(
      tile('Contact to appointment').getByText('No previous data'),
    ).toBeVisible();
    expect(
      tile('Appointment to show').getByText('0 pts, was 70%'),
    ).toBeVisible();
  });

  it('counts a faster first response as good and a lower show-to-sale as bad', async () => {
    fetchKpis.mockResolvedValue(report);
    renderWithProviders(<KpiDashboard />);
    await screen.findByText('From lead to sale');

    const faster = tile('First response time').getByText('-8 min, was 20 min');
    expect(faster.closest('p')).toHaveClass('text-success');
    const lower = tile('Show to sale').getByText('-5 pts, was 30%');
    expect(lower.closest('p')).toHaveClass('text-danger');
  });

  it('describes the funnel in words and offers it as a table', async () => {
    fetchKpis.mockResolvedValue(report);
    renderWithProviders(<KpiDashboard />);
    await screen.findByText('From lead to sale');

    expect(
      screen.getByRole('img', {
        name: '40 leads, 20 contacted, 10 with an appointment, 7 showed, 2 sales.',
      }),
    ).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', {name: 'Show as table'}));
    const row = screen.getByRole('row', {name: /Appointment booked/});
    expect(within(row).getByText('10')).toBeVisible();
    expect(within(row).getByText('50%')).toBeVisible();
    expect(screen.getByRole('button', {name: 'Show as chart'})).toBeVisible();
  });

  it('says so when the period has no leads', async () => {
    fetchKpis.mockResolvedValue({
      ...report,
      kpis: report.kpis.map((k) => ({...k, value: null, previous_value: null})),
      funnel: report.funnel.map((f) => ({...f, count: 0})),
    });
    renderWithProviders(<KpiDashboard />);
    expect(await screen.findByText('No leads in this period.')).toBeVisible();
    expect(screen.getAllByText('No data')).toHaveLength(7);
  });

  it('offers a retry when the report cannot load', async () => {
    fetchKpis
      .mockRejectedValueOnce(new Error('down'))
      .mockResolvedValue(report);
    renderWithProviders(<KpiDashboard />);
    await userEvent.click(
      await screen.findByRole('button', {name: 'Try again'}),
    );
    expect(await screen.findByText('From lead to sale')).toBeVisible();
  });
});
