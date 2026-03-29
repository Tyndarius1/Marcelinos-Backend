{{--
Print Report Partial Template — Tourism Edition
Variables:
$title (string)
$subtitle (string)
$localData (Collection: region, province, municipality, barangay, total)
$foreignData (Collection: country, total)
--}}

@php
    // Domestic Grouping & Sorting
    $regionGroups = $localData->groupBy(function($item) { return $item->region ?: 'Unknown'; });
    
    // Sort regions by total bookings descending
    $regionTotals = $regionGroups->map(function ($rows) {
        return $rows->sum('total');
    })->sortDesc();
    
    $topLocalName = $regionTotals->keys()->first();
    $topLocal = $topLocalName && $regionTotals[$topLocalName] > 0 ? (object)[
        'region' => $topLocalName === 'Unknown' ? 'Not Specified' : $topLocalName,
        'province' => 'Top Region',
        'municipality' => '',
        'total' => $regionTotals[$topLocalName]
    ] : null;

    $sortedLocalData = $localData->sortByDesc(function ($stat) use ($regionTotals) {
        $regionKey = $stat->region ?: 'Unknown';
        return $regionTotals[$regionKey] * 1000000 + $stat->total;
    })->values();

    $regionRanks = $regionTotals->keys()->toArray();

    // Foreign Grouping & Sorting
    $countryGroups = $foreignData->groupBy(function($item) { return $item->country ?: 'Unknown'; });
    
    $countryTotals = $countryGroups->map(function ($rows) {
        return $rows->sum('total');
    })->sortDesc();

    $topForeignName = $countryTotals->keys()->first();
    $topForeign = $topForeignName && $countryTotals[$topForeignName] > 0 ? (object)[
        'country' => $topForeignName === 'Unknown' ? 'Not Specified' : $topForeignName,
        'total' => $countryTotals[$topForeignName]
    ] : null;

    $sortedForeignData = $foreignData->sortByDesc(function ($stat) use ($countryTotals) {
        $countryKey = $stat->country ?: 'Unknown';
        return $countryTotals[$countryKey] * 1000000 + $stat->total;
    })->values();

    $countryRanks = $countryTotals->keys()->toArray();
@endphp

<div class="print-report" style="font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif; color: #1a1a2e; background: #fff; padding: 0 24px 24px; margin: 0 auto; max-width: 210mm; box-sizing: border-box;">

    {{-- ===== REPORT HEADER WITH LOGO ===== --}}
    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 3px solid #15803d;">
        <div style="flex-shrink: 0;">
            <img src="{{ $logoSrc ?? asset('brand-logo.webp') }}" alt="Marcelinos Resort and Hotel" style="height: 64px; width: auto; display: block;" />
        </div>
        <div style="flex: 1; min-width: 0;">
            <div style="font-size: 10px; font-weight: 700; letter-spacing: 1.2px; color: #15803d; text-transform: uppercase; margin-bottom: 2px;">Tourism Demographics Report</div>
            <h1 style="font-size: 20px; font-weight: 800; margin: 0 0 4px; color: #0f172a; letter-spacing: 0.3px;">{{ $title }}</h1>
            <p style="font-size: 12px; color: #64748b; margin: 0 0 2px;">{{ $subtitle }}</p>
            <p style="font-size: 10px; color: #94a3b8; margin: 0;">Confidential — For Tourism Office Use Only</p>
        </div>
    </div>

    {{-- ===== SUMMARY HIGHLIGHT BOXES ===== --}}
    @if($topLocal || $topForeign)
        <div style="display: flex; gap: 12px; margin-bottom: 24px;">
            @if($topLocal)
                <div
                    style="flex: 1; background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 5px solid #16a34a; border-radius: 6px; padding: 12px 16px;">
                    <div
                        style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #16a34a; margin-bottom: 4px;">
                        Top Domestic Region</div>
                    <div style="font-size: 18px; font-weight: 800; color: #14532d;">{{ $topLocal->region ?: 'N/A' }}</div>
                    <div style="font-size: 12px; color: #22c55e; margin-top: 2px;">
                        {{ $topLocal->province ?: '' }}{{ $topLocal->province ? ' — ' : '' }}{{ $topLocal->municipality ?: '' }}
                    </div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px; font-weight: 600;">{{ $topLocal->total }}
                        booking(s) · Ranked #1</div>
                </div>
            @endif
            @if($topForeign)
                <div
                    style="flex: 1; background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 5px solid #16a34a; border-radius: 6px; padding: 12px 16px;">
                    <div
                        style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #16a34a; margin-bottom: 4px;">
                        Top International</div>
                    <div style="font-size: 18px; font-weight: 800; color: #14532d;">{{ $topForeign->country ?: 'N/A' }}</div>
                    <div style="font-size: 12px; color: #22c55e; margin-top: 2px;">Country of Origin</div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px; font-weight: 600;">{{ $topForeign->total }}
                        booking(s) · Ranked #1</div>
                </div>
            @endif
            <div
                style="flex: 0.6; background: #fafafa; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; text-align: center;">
                <div
                    style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 6px;">
                    Total Bookings</div>
                <div style="font-size: 30px; font-weight: 900; color: #15803d; line-height: 1;">
                    {{ $localData->sum('total') + $foreignData->sum('total') }}</div>
                <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">{{ $localData->sum('total') }} local
                    &nbsp;+&nbsp; {{ $foreignData->sum('total') }} foreign</div>
            </div>
        </div>
    @endif

    {{-- ===== DOMESTIC TABLE ===== --}}
    <div style="margin-bottom: 32px;">
        <div
            style="display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #15803d; padding-bottom: 6px; margin-bottom: 12px;">
            <h2
                style="font-size: 15px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #14532d; margin: 0;">
                Domestic Tourists
            </h2>
            <span
                style="font-size: 12px; font-weight: 700; background: #dcfce7; color: #15803d; padding: 3px 12px; border-radius: 20px;">
                {{ $localData->sum('total') }} Total
            </span>
        </div>

        @if($localData->isEmpty())
            <p
                style="text-align:center; color: #94a3b8; font-style: italic; padding: 20px; border: 1px dashed #e2e8f0; border-radius: 6px;">
                No domestic guest records for this period.
            </p>
        @else
            @php $prevRegion = null; @endphp
            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr style="background: #15803d; color: white;">
                        <th
                            style="padding: 9px 10px; text-align: center; width: 36px; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; border-right: 1px solid #16a34a;">
                            #</th>
                        <th
                            style="padding: 9px 12px; text-align: left; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; border-right: 1px solid #16a34a;">
                            REGION</th>
                        <th
                            style="padding: 9px 12px; text-align: left; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; border-right: 1px solid #16a34a;">
                            PROVINCE</th>
                        <th
                            style="padding: 9px 12px; text-align: left; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; border-right: 1px solid #16a34a;">
                            MUNICIPALITY / CITY</th>
                        <th
                            style="padding: 9px 12px; text-align: left; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; border-right: 1px solid #16a34a;">
                            BARANGAY</th>
                        <th
                            style="padding: 9px 12px; text-align: center; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; width: 80px;">
                            BOOKINGS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sortedLocalData as $stat)
                        @php
                            $regionKey = $stat->region ?: 'Unknown';
                            $isNewRegion = $regionKey !== $prevRegion;
                            $prevRegion = $regionKey;
                            $regionRank = array_search($regionKey, $regionRanks) + 1;
                            
                            $rowBg = $regionRank % 2 === 0 ? '#f8fafc' : '#ffffff';
                            $isTop = $regionRank === 1;
                        @endphp
                        <tr style="background: {{ $isTop ? '#f0fdf4' : $rowBg }}; {{ $isTop ? 'font-weight: 700;' : '' }}">
                            <td
                                style="padding: 8px 10px; text-align: center; border: 1px solid #e2e8f0; border-right: 2px solid #bbf7d0; color: {{ $isTop ? '#15803d' : '#94a3b8' }}; font-weight: bold; font-size: 11px;">
                                @if($isNewRegion)
                                    @if($isTop) #1 @elseif($regionRank === 2) #2 @elseif($regionRank === 3) #3 @else {{ $regionRank }} @endif
                                @endif
                            </td>
                            <td
                                style="padding: 8px 12px; border: 1px solid #e2e8f0; font-weight: {{ $isTop ? '800' : '600' }}; color: {{ $isTop ? '#14532d' : '#374151' }}; font-size: {{ $isTop ? '13px' : '12px' }};">
                                @if($isNewRegion)
                                    {{ $stat->region ?: 'Not Specified' }}
                                    @if($regionTotals[$regionKey] !== $stat->total)
                                        <div style="font-size: 9px; color: #64748b; margin-top: 2px;">{{ $regionTotals[$regionKey] }} total</div>
                                    @endif
                                @endif
                            </td>
                            <td style="padding: 8px 12px; border: 1px solid #e2e8f0; color: #4b5563;">
                                {{ $stat->province ?: '—' }}</td>
                            <td style="padding: 8px 12px; border: 1px solid #e2e8f0; color: #4b5563;">
                                {{ $stat->municipality ?: '—' }}</td>
                            <td style="padding: 8px 12px; border: 1px solid #e2e8f0; color: #4b5563;">
                                {{ $stat->barangay ?: '—' }}</td>
                            <td
                                style="padding: 8px 12px; border: 1px solid #e2e8f0; text-align: center; font-weight: bold; font-size: {{ $isTop ? '15px' : '13px' }}; color: {{ $isTop ? '#15803d' : '#1f2937' }};">
                                {{ $stat->total }}
                            </td>
                        </tr>
                    @endforeach
                    {{-- Grand Total Row --}}
                    <tr style="background: #15803d; color: white; font-weight: 800;">
                        <td colspan="5"
                            style="padding: 9px 12px; text-align: right; font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase; border: 1px solid #166534;">
                            Grand Total (Domestic)
                        </td>
                        <td style="padding: 9px 12px; text-align: center; font-size: 15px; border: 1px solid #166534;">
                            {{ $localData->sum('total') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        @endif
    </div>

    {{-- ===== INTERNATIONAL TABLE ===== --}}
    <div>
        <div
            style="display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #16a34a; padding-bottom: 6px; margin-bottom: 12px;">
            <h2
                style="font-size: 15px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #14532d; margin: 0;">
                International Tourists
            </h2>
            <span
                style="font-size: 12px; font-weight: 700; background: #dcfce7; color: #16a34a; padding: 3px 12px; border-radius: 20px;">
                {{ $foreignData->sum('total') }} Total
            </span>
        </div>

        @if($foreignData->isEmpty())
            <p
                style="text-align:center; color: #94a3b8; font-style: italic; padding: 20px; border: 1px dashed #e2e8f0; border-radius: 6px;">
                No international guest records for this period.
            </p>
        @else
            @php $prevCountry = null; @endphp
            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr style="background: #16a34a; color: white;">
                        <th
                            style="padding: 9px 10px; text-align: center; width: 36px; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; border-right: 1px solid #15803d;">
                            #</th>
                        <th
                            style="padding: 9px 12px; text-align: left; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; border-right: 1px solid #15803d;">
                            COUNTRY OF ORIGIN</th>
                        <th
                            style="padding: 9px 12px; text-align: center; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; width: 80px;">
                            BOOKINGS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sortedForeignData as $stat)
                        @php 
                            $countryKey = $stat->country ?: 'Unknown';
                            $isNewCountry = $countryKey !== $prevCountry;
                            $prevCountry = $countryKey;
                            $countryRank = array_search($countryKey, $countryRanks) + 1;
                            $iTop = $countryRank === 1; 
                        @endphp
                        <tr style="background: {{ $iTop ? '#f0fdf4' : ($countryRank % 2 === 0 ? '#f8fafc' : '#ffffff') }};">
                            <td
                                style="padding: 8px 10px; text-align: center; border: 1px solid #e2e8f0; border-right: 2px solid #bbf7d0; color: {{ $iTop ? '#16a34a' : '#94a3b8' }}; font-weight: bold; font-size: 11px;">
                                @if($isNewCountry)
                                    @if($iTop) #1 @elseif($countryRank === 2) #2 @elseif($countryRank === 3) #3 @else {{ $countryRank }} @endif
                                @endif
                            </td>
                            <td
                                style="padding: 8px 12px; border: 1px solid #e2e8f0; font-weight: {{ $iTop ? '800' : '600' }}; color: {{ $iTop ? '#14532d' : '#374151' }}; font-size: {{ $iTop ? '13px' : '12px' }};">
                                @if($isNewCountry)
                                    {{ $stat->country ?: 'Not Specified' }}
                                    @if($countryTotals[$countryKey] !== $stat->total)
                                        <div style="font-size: 9px; color: #64748b; margin-top: 2px;">{{ $countryTotals[$countryKey] }} total</div>
                                    @endif
                                @endif
                            </td>
                            <td
                                style="padding: 8px 12px; border: 1px solid #e2e8f0; text-align: center; font-weight: bold; font-size: {{ $iTop ? '15px' : '13px' }}; color: {{ $iTop ? '#16a34a' : '#1f2937' }};">
                                {{ $stat->total }}
                            </td>
                        </tr>
                    @endforeach
                    <tr style="background: #16a34a; color: white; font-weight: 800;">
                        <td colspan="2"
                            style="padding: 9px 12px; text-align: right; font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase; border: 1px solid #15803d;">
                            Grand Total (International)
                        </td>
                        <td style="padding: 9px 12px; text-align: center; font-size: 15px; border: 1px solid #15803d;">
                            {{ $foreignData->sum('total') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        @endif
    </div>

    {{-- ===== FOOTER ===== --}}
    <div
        style="margin-top: 32px; padding-top: 12px; border-top: 2px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; font-size: 10px; color: #64748b;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <img src="{{ $logoSrc ?? asset('brand-logo.webp') }}" alt="" style="height: 28px; width: auto;" aria-hidden="true" />
            <span><strong style="color: #0f172a;">Marcelinos Resort and Hotel</strong> · Confidential</span>
        </div>
        <div style="text-align: right;">
            Printed {{ now()->format('F j, Y  g:i A') }} · System-generated report
        </div>
    </div>

</div>