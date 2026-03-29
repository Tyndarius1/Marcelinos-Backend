<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestDemographicsExportController extends Controller
{
    public function pdf(Request $request)
    {
        $type = (string) $request->query('type', 'overview_selected');
        $period = $request->query('period');
        $period = $period === 'null' ? null : $period;

        $unpaidStatuses = [Booking::STATUS_UNPAID];
        $successfulStatuses = [Booking::STATUS_PAID, Booking::STATUS_COMPLETED, Booking::STATUS_OCCUPIED];

        $now = Carbon::now();

        [$start, $end, $title, $subtitle, $statuses] = match ($type) {
            'unpaid' => $this->resolvePresetReport($period, $unpaidStatuses, 'Demographics Report: Unpaid Bookings (Pending)', $now),
            'successful' => $this->resolvePresetReport($period, $successfulStatuses, 'Demographics Report: Successful Bookings (Paid/Confirmed)', $now),
            'overview_selected' => $this->resolveOverviewReport($request, $successfulStatuses, $now),
            default => $this->resolveOverviewReport($request, $successfulStatuses, $now),
        };

        $rows = $this->getHierarchicalData($statuses, $start, $end);
        $localData = $rows->where('is_international', false)->values();
        $foreignData = $rows->where('is_international', true)->values();

        $logoPath = public_path('brand-logo.webp');
        $logoSrc = null;
        if (is_file($logoPath)) {
            $logoSrc = 'data:image/webp;base64,' . base64_encode((string) file_get_contents($logoPath));
        }

        $pdf = Pdf::loadView('reports.guest-demographics-pdf', [
            'title' => $title,
            'subtitle' => $subtitle,
            'localData' => $localData,
            'foreignData' => $foreignData,
            'logoSrc' => $logoSrc,
        ])->setPaper('a4', 'portrait');

        $safePeriod = $period ? Str::slug((string) $period) : 'selected';
        $filename = 'guest-demographics-' . Str::slug($type) . '-' . $safePeriod . '-' . $now->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }

    private function resolvePresetReport($period, array $statuses, string $baseTitle, Carbon $now): array
    {
        $period = (string) ($period ?: 'today');

        [$start, $end, $label] = match ($period) {
            'today' => [Carbon::today(), Carbon::today(), 'Today'],
            'next_7_days' => [Carbon::tomorrow(), Carbon::today()->addDays(7), 'Next 7 Days'],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'This Month'],
            'next_month' => [$now->copy()->addMonth()->startOfMonth(), $now->copy()->addMonth()->endOfMonth(), 'Next Month'],
            'all' => [$now->copy()->subYears(10), $now->copy()->addYears(10), 'All Time'],
            default => [Carbon::today(), Carbon::today(), 'Today'],
        };

        $subtitle = 'Period: ' . $label . '  ·  Generated: ' . $now->format('F j, Y, g:i a');

        return [$start, $end, $baseTitle, $subtitle, $statuses];
    }

    private function resolveOverviewReport(Request $request, array $statuses, Carbon $now): array
    {
        $start = $request->query('start') ? Carbon::parse((string) $request->query('start')) : $now->copy()->startOfMonth();
        $end = $request->query('end') ? Carbon::parse((string) $request->query('end')) : $now->copy()->endOfMonth();

        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        $title = 'Comprehensive Demographics Report';
        $subtitle = $this->overviewLabel($start, $end) . '  ·  Generated: ' . $now->format('F j, Y, g:i a');

        return [$start, $end, $title, $subtitle, $statuses];
    }

    private function overviewLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($start->copy()->startOfMonth()) && $end->isSameDay($start->copy()->endOfMonth())) {
            return 'Month: ' . $start->format('F Y');
        }

        if ($start->isSameDay($start->copy()->startOfYear()) && $end->isSameDay($start->copy()->endOfYear())) {
            return 'Year: ' . $start->format('Y');
        }

        return 'Dates: ' . $start->toDateString() . ' → ' . $end->toDateString();
    }

    private function getHierarchicalData(array $statusGroup, Carbon $startDate, Carbon $endDate)
    {
        return Booking::select(
            'guests.is_international',
            'guests.country',
            'guests.region',
            'guests.province',
            'guests.municipality',
            DB::raw('count(*) as total')
        )
            ->join('guests', 'bookings.guest_id', '=', 'guests.id')
            ->whereIn('bookings.status', $statusGroup)
            ->whereBetween('bookings.check_in', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->groupBy('guests.is_international', 'guests.country', 'guests.region', 'guests.province', 'guests.municipality')
            ->orderByRaw('guests.is_international ASC, total DESC, guests.region ASC')
            ->get();
    }
}

