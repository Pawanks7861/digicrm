<?php

namespace App\Services;

use App\Events\PerformanceReportApproved;
use App\Events\PerformanceReportGenerated;
use App\Events\PerformanceReportPublished;
use App\Models\Client;
use App\Models\ClientPerformanceReport;
use App\Models\ClientReportMetric;
use App\Models\ReportTemplateSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PerformanceReportService
{
    public function __construct(
        protected MarketingPerformanceService $performance
    ) {
    }

    public function generateDraft(
        Client $client,
        string $periodStart,
        string $periodEnd,
        User $actor,
        ?string $title = null,
        bool $includeAdSpend = true,
        bool $includeConversionValue = true
    ): ClientPerformanceReport {
        $dashboard = $this->performance->clientDashboard($client->id, $periodStart, $periodEnd);
        $template = ReportTemplateSetting::current();

        return DB::transaction(function () use ($client, $periodStart, $periodEnd, $actor, $title, $dashboard, $template, $includeAdSpend, $includeConversionValue) {
            $report = ClientPerformanceReport::create([
                'client_id' => $client->id,
                'title' => $title ?: ('Performance '.$periodStart.' – '.$periodEnd),
                'report_number' => $this->nextNumber(),
                'status' => ClientPerformanceReport::STATUS_DRAFT,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'section_config' => [
                    'sections' => $template->default_sections,
                    'show_ad_spend' => $includeAdSpend,
                    'show_conversion_value' => $includeConversionValue,
                    'coverage' => $dashboard['coverage'],
                    'freshness' => $dashboard['freshness'],
                ],
                'currency' => $this->detectCurrency($dashboard['metrics']),
                'generated_at' => now(),
                'created_by' => $actor->id,
            ]);

            $this->freezeMetrics($report, $dashboard['metrics'], $includeAdSpend, $includeConversionValue);

            event(new PerformanceReportGenerated($report));

            return $report->fresh('metrics');
        });
    }

    protected function freezeMetrics(ClientPerformanceReport $report, array $metrics, bool $includeAdSpend, bool $includeConversionValue): void
    {
        ClientReportMetric::where('client_performance_report_id', $report->id)->delete();

        $order = 0;
        foreach ($metrics as $code => $m) {
            if (! $includeAdSpend && in_array($code, ['spend', 'cpc', 'cpl', 'cpa'], true)) {
                continue;
            }
            if (! $includeConversionValue && in_array($code, ['attributed_revenue', 'roas'], true)) {
                continue;
            }

            ClientReportMetric::create([
                'client_performance_report_id' => $report->id,
                'section' => $this->sectionFor($code),
                'metric_code' => $code,
                'label' => $m['label'] ?? $code,
                'metric_value' => $m['value'],
                'display_value' => $m['display'] ?? MetricAggregationService::NA,
                'unit' => null,
                'currency' => $m['currency'] ?? null,
                'aggregation' => $m['aggregation'] ?? null,
                'formula_label' => $m['formula_label'] ?? null,
                'sort_order' => $order++,
            ]);
        }
    }

    protected function sectionFor(string $code): string
    {
        return match (true) {
            in_array($code, ['impressions', 'reach', 'views'], true) => 'awareness',
            in_array($code, ['clicks', 'likes', 'comments', 'shares', 'engagements', 'ctr', 'engagement_rate_by_reach', 'engagement_rate_by_impressions', 'engagement_rate_by_followers'], true) => 'engagement',
            in_array($code, ['sessions', 'users', 'pageviews'], true) => 'traffic',
            in_array($code, ['leads', 'cpl'], true) => 'leads',
            in_array($code, ['conversions', 'cpa', 'conversion_rate', 'attributed_revenue'], true) => 'conversions',
            in_array($code, ['spend', 'cpc', 'roas'], true) => 'paid',
            in_array($code, ['followers', 'subscribers'], true) => 'social_growth',
            default => 'summary',
        };
    }

    protected function detectCurrency(array $metrics): ?string
    {
        foreach ($metrics as $m) {
            if (! empty($m['currency']) && $m['currency'] !== 'mixed') {
                return $m['currency'];
            }
        }

        return null;
    }

    protected function nextNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "PERF-{$year}-";
        $last = ClientPerformanceReport::withTrashed()
            ->where('report_number', 'like', $prefix.'%')
            ->orderByDesc('report_number')
            ->value('report_number');
        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function submitForReview(ClientPerformanceReport $report): ClientPerformanceReport
    {
        $this->assertMutable($report);
        $report->update(['status' => ClientPerformanceReport::STATUS_INTERNAL_REVIEW]);

        return $report->fresh();
    }

    public function approve(ClientPerformanceReport $report, User $actor): ClientPerformanceReport
    {
        if ($report->status !== ClientPerformanceReport::STATUS_INTERNAL_REVIEW
            && $report->status !== ClientPerformanceReport::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Report cannot be approved from current status.']);
        }
        $report->update([
            'status' => ClientPerformanceReport::STATUS_APPROVED,
            'approved_by' => $actor->id,
        ]);
        event(new PerformanceReportApproved($report));

        return $report->fresh();
    }

    public function publish(ClientPerformanceReport $report, User $actor): ClientPerformanceReport
    {
        if ($report->status !== ClientPerformanceReport::STATUS_APPROVED
            && $report->status !== ClientPerformanceReport::STATUS_DRAFT) {
            // Allow draft→publish only via explicit approve path preferred; still allow approved
            throw ValidationException::withMessages(['status' => 'Approve before publishing.']);
        }
        if ($report->status === ClientPerformanceReport::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Approve before publishing.']);
        }

        $report->update([
            'status' => ClientPerformanceReport::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_by' => $actor->id,
        ]);
        event(new PerformanceReportPublished($report));

        return $report->fresh();
    }

    public function archive(ClientPerformanceReport $report): ClientPerformanceReport
    {
        $report->update(['status' => ClientPerformanceReport::STATUS_ARCHIVED]);

        return $report->fresh();
    }

    public function assertMutable(ClientPerformanceReport $report): void
    {
        if ($report->isImmutable()) {
            throw ValidationException::withMessages(['status' => 'Published reports are immutable.']);
        }
    }

    /** PDF from frozen snapshots only. */
    public function pdf(ClientPerformanceReport $report): Response
    {
        $report->load(['client', 'metrics']);
        $template = ReportTemplateSetting::current();

        return Pdf::loadView('pdf.client_performance_report', [
            'report' => $report,
            'client' => $report->client,
            'metrics' => $report->metrics,
            'template' => $template,
        ])->download(($report->report_number ?: 'performance').'.pdf');
    }
}
