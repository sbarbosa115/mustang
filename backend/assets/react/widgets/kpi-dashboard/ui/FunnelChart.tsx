import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Bar, BarChart, LabelList, XAxis, YAxis} from 'recharts';
import type {Schema} from '@/shared/api';
import {Button} from '@/shared/ui/button';
import {Card, CardContent, CardHeader, CardTitle} from '@/shared/ui/card';
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from '@/shared/ui/chart';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/shared/ui/table';
import {formatNumber} from '../model/format';

type Step = Schema<'FunnelStepOutput'>;

/** One series, so one colour (chart-1): the length of the bar and its number say the rest. */
export function FunnelChart({steps}: {steps: Step[]}) {
  const {t} = useTranslation('reports');
  const [asTable, setAsTable] = useState(false);
  const rows = steps.map((s, i) => {
    const before = i === 0 ? null : (steps[i - 1]?.count ?? 0);
    return {
      code: s.code,
      label: t(`funnel.${s.code}`),
      count: s.count,
      share:
        before == null || before === 0
          ? null
          : Math.round((s.count * 1000) / before) / 10,
    };
  });
  const config: ChartConfig = {
    count: {label: t('funnel.count'), color: 'var(--chart-1)'},
  };
  const summary = t('funnel.summary', {
    leads: steps.find((s) => s.code === 'leads')?.count ?? 0,
    contacted: steps.find((s) => s.code === 'contacted')?.count ?? 0,
    appointments: steps.find((s) => s.code === 'appointments')?.count ?? 0,
    shows: steps.find((s) => s.code === 'shows')?.count ?? 0,
    sales: steps.find((s) => s.code === 'sales')?.count ?? 0,
  });
  const empty = (steps[0]?.count ?? 0) === 0;

  return (
    <Card>
      <CardHeader className="flex flex-row items-start justify-between gap-3">
        <div className="space-y-1">
          <CardTitle className="text-lg">{t('funnel.title')}</CardTitle>
          <p className="text-sm text-muted-foreground">
            {t('funnel.description')}
          </p>
        </div>
        {!empty && (
          <Button
            type="button"
            variant="outline"
            onClick={() => setAsTable((v) => !v)}
          >
            {asTable ? t('funnel.showChart') : t('funnel.showTable')}
          </Button>
        )}
      </CardHeader>
      <CardContent>
        {empty ? (
          <p className="py-8 text-center text-sm text-muted-foreground">
            {t('funnel.empty')}
          </p>
        ) : asTable ? (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('funnel.step')}</TableHead>
                <TableHead className="text-right">
                  {t('funnel.count')}
                </TableHead>
                <TableHead className="text-right">
                  {t('funnel.ofPrevious')}
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={r.code}>
                  <TableCell>{r.label}</TableCell>
                  <TableCell className="tabular text-right">
                    {formatNumber(r.count)}
                  </TableCell>
                  <TableCell className="tabular text-right">
                    {r.share == null
                      ? t('funnel.none')
                      : t('value.percent', {value: formatNumber(r.share)})}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        ) : (
          <ChartContainer
            config={config}
            role="img"
            aria-label={summary}
            className="aspect-auto h-64 w-full"
          >
            <BarChart
              data={rows}
              layout="vertical"
              margin={{left: 8, right: 40}}
            >
              <XAxis type="number" hide domain={[0, 'dataMax']} />
              <YAxis
                type="category"
                dataKey="label"
                width={130}
                tickLine={false}
                axisLine={false}
              />
              <ChartTooltip
                cursor={false}
                content={<ChartTooltipContent hideIndicator />}
              />
              <Bar
                dataKey="count"
                fill="var(--color-count)"
                radius={[0, 4, 4, 0]}
                barSize={22}
              >
                <LabelList
                  dataKey="count"
                  position="right"
                  className="fill-foreground tabular"
                />
              </Bar>
            </BarChart>
          </ChartContainer>
        )}
      </CardContent>
    </Card>
  );
}
