import {screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {Users} from 'lucide-react';
import {describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '../../test/render';
import {ChannelActions} from './ChannelActions';
import {ConsentIndicator} from './ConsentIndicator';
import {DataTable} from './DataTable';
import {KpiTile} from './KpiTile';
import {PriorityItem} from './PriorityItem';
import {StageBadge} from './StageBadge';
import {VehicleCard} from './VehicleCard';

describe('StageBadge', () => {
  it('shows the stage name and days in stage, never colour alone', () => {
    renderWithProviders(<StageBadge stage="engaged" daysInStage={6} />);
    expect(screen.getByText('Engaged')).toBeInTheDocument();
    expect(screen.getByText('· 6 days')).toBeInTheDocument();
  });
});

describe('ConsentIndicator', () => {
  it('names the state of every channel in words', () => {
    renderWithProviders(
      <ConsentIndicator
        availability={{text: 'granted', email: 'opted_out', call: 'missing'}}
      />,
    );
    expect(screen.getByText('Text: Granted')).toBeInTheDocument();
    expect(screen.getByText('Email: Opted out')).toBeInTheDocument();
    expect(screen.getByText('Call: Missing')).toBeInTheDocument();
  });
});

describe('ChannelActions', () => {
  it('disables a channel without consent and says why', async () => {
    const onSelect = vi.fn();
    renderWithProviders(
      <ChannelActions
        availability={{text: 'granted', email: 'revoked', call: 'missing'}}
        onSelect={onSelect}
      />,
    );
    await userEvent.click(screen.getByRole('button', {name: 'Send a text'}));
    expect(onSelect).toHaveBeenCalledWith('text');
    expect(
      screen.getByRole('button', {
        name: 'Send an email: Consent for Email was revoked',
      }),
    ).toBeDisabled();
    expect(
      screen.getByRole('button', {name: 'Call: No consent recorded for Call'}),
    ).toBeDisabled();
  });
});

describe('KpiTile', () => {
  it('reads a drop in response time as good news', () => {
    renderWithProviders(
      <KpiTile
        label="First response"
        value="12 min"
        delta={-3}
        higherIsBetter={false}
      />,
    );
    expect(screen.getByText('12 min')).toBeInTheDocument();
    expect(
      screen.getByText('Down from the previous period').parentElement,
    ).toHaveClass('text-success');
  });

  it('says when there is nothing to compare', () => {
    renderWithProviders(<KpiTile label="Units" value="0" delta={null} />);
    expect(screen.getByText('Same as the previous period')).toBeInTheDocument();
  });
});

describe('PriorityItem', () => {
  it('marks a hot row with words, not only colour', () => {
    renderWithProviders(
      <PriorityItem
        customerName="Sarah Johnson"
        customerHref="/customers/1"
        reason="Replied 47 min ago; nobody answered"
        hot
      />,
    );
    expect(screen.getByRole('link', {name: 'Sarah Johnson'})).toHaveAttribute(
      'href',
      '/customers/1',
    );
    expect(screen.getByText('Contact now')).toBeInTheDocument();
    expect(
      screen.getByText('Replied 47 min ago; nobody answered'),
    ).toBeInTheDocument();
  });
});

describe('VehicleCard', () => {
  it('shows price and mileage, or asks to call for a price', () => {
    renderWithProviders(
      <VehicleCard
        title="2024 Toyota Camry"
        stockNumber="T1001"
        mileage={12345}
        priceCents={2899900}
        condition="used"
        status="available"
      />,
    );
    expect(screen.getByText('$28,999')).toBeInTheDocument();
    expect(screen.getByText('12,345 mi')).toBeInTheDocument();
  });
});

describe('DataTable', () => {
  const columns = [{accessorKey: 'name', header: 'Name'}];
  const empty = {icon: Users, title: 'No customers yet.'};

  it('shows skeletons while loading', () => {
    renderWithProviders(
      <DataTable columns={columns} data={undefined} isLoading empty={empty} />,
    );
    expect(screen.getByLabelText('Loading')).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('offers a retry when the list fails', async () => {
    const onRetry = vi.fn();
    renderWithProviders(
      <DataTable
        columns={columns}
        data={undefined}
        isLoading={false}
        error={new Error('x')}
        onRetry={onRetry}
        empty={empty}
      />,
    );
    await userEvent.click(screen.getByRole('button', {name: 'Try again'}));
    expect(onRetry).toHaveBeenCalled();
  });

  it('tells empty apart from filtered to nothing, and clears the filters', async () => {
    const onClear = vi.fn();
    const {rerender} = renderWithProviders(
      <DataTable columns={columns} data={[]} isLoading={false} empty={empty} />,
    );
    expect(screen.getByText('No customers yet.')).toBeInTheDocument();
    rerender(
      <DataTable
        columns={columns}
        data={[]}
        isLoading={false}
        filtered
        onClearFilters={onClear}
        empty={empty}
      />,
    );
    await userEvent.click(screen.getByRole('button', {name: 'Show all'}));
    expect(onClear).toHaveBeenCalled();
  });

  it('renders one row per item', () => {
    renderWithProviders(
      <DataTable
        columns={columns}
        data={[{name: 'Sarah'}, {name: 'Michael'}]}
        isLoading={false}
        empty={empty}
      />,
    );
    expect(screen.getByRole('cell', {name: 'Sarah'})).toBeInTheDocument();
    expect(screen.getByRole('cell', {name: 'Michael'})).toBeInTheDocument();
  });
});
