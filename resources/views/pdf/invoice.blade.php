<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice #{{ $invoice['invoicenum'] ?: $invoice['invoiceid'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            color: #333;
            line-height: 1.4;
        }
        .page { padding: 40px 50px; }

        /* ── Header ── */
        .header { width: 100%; margin-bottom: 30px; }
        .header td { vertical-align: top; }
        .company-name {
            font-size: 16px;
            font-weight: 700;
            color: #111;
        }
        .company-sub {
            font-size: 8px;
            color: #888;
            margin-top: 2px;
        }
        .inv-label {
            font-size: 20px;
            font-weight: 700;
            color: #111;
            text-align: right;
        }

        /* ── Sender line (small above recipient) ── */
        .sender-line {
            font-size: 7px;
            color: #999;
            border-bottom: 1px solid #ccc;
            padding-bottom: 2px;
            margin-bottom: 8px;
        }

        /* ── Meta section ── */
        .meta { width: 100%; margin-bottom: 24px; }
        .meta td { vertical-align: top; font-size: 9px; }
        .meta-label {
            font-size: 7px;
            font-weight: 700;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .address { line-height: 1.6; color: #333; }
        .address strong { font-weight: 600; color: #111; }

        /* ── Details table (right side) ── */
        .details { border-collapse: collapse; }
        .details td {
            padding: 3px 0;
            font-size: 9px;
        }
        .details td:first-child {
            color: #666;
            padding-right: 16px;
            white-space: nowrap;
        }
        .details td:last-child {
            font-weight: 600;
            color: #111;
            text-align: right;
        }

        /* ── Status ── */
        .badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .b-paid { background: #e6f4ea; color: #1a7431; }
        .b-unpaid { background: #fef7e0; color: #8a6d00; }
        .b-overdue { background: #fce8e6; color: #a50e0e; }
        .b-cancelled { background: #f1f3f4; color: #5f6368; }
        .b-refunded { background: #e8eaf6; color: #3f51b5; }

        /* ── Line items ── */
        .items { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .items thead th {
            border-top: 1px solid #999;
            border-bottom: 1px solid #999;
            padding: 6px 8px;
            font-size: 8px;
            font-weight: 700;
            color: #333;
            text-transform: uppercase;
            text-align: left;
        }
        .items thead th:last-child { text-align: right; }
        .items tbody td {
            padding: 6px 8px;
            font-size: 9px;
            border-bottom: 1px solid #e5e5e5;
            color: #333;
        }
        .items tbody td:first-child { color: #888; width: 4%; }
        .items tbody td:last-child { text-align: right; font-weight: 600; }

        /* ── Totals ── */
        .totals-wrap { width: 100%; margin-bottom: 20px; }
        .totals { border-collapse: collapse; width: 100%; }
        .totals td { padding: 4px 8px; font-size: 9px; }
        .totals td:first-child { color: #666; }
        .totals td:last-child { text-align: right; font-weight: 600; color: #111; }
        .totals .t-final td {
            border-top: 1px solid #999;
            font-size: 11px;
            font-weight: 700;
            padding-top: 6px;
        }
        .totals .t-final td:first-child { color: #111; }
        .totals .t-balance td {
            font-size: 10px;
            font-weight: 700;
        }
        .totals .t-balance td:last-child { color: #c00; }

        /* ── Paid box ── */
        .paid-box {
            border: 1px solid #c3e6cb;
            background: #f0f9f2;
            padding: 8px 12px;
            margin-bottom: 16px;
            font-size: 9px;
        }
        .paid-box strong { color: #1a7431; }
        .paid-box span { color: #333; }

        /* ── Transactions ── */
        .tx { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .tx thead th {
            border-bottom: 1px solid #ccc;
            padding: 4px 8px;
            font-size: 8px;
            font-weight: 700;
            color: #555;
            text-transform: uppercase;
            text-align: left;
        }
        .tx thead th:last-child { text-align: right; }
        .tx tbody td {
            padding: 5px 8px;
            font-size: 9px;
            color: #555;
            border-bottom: 1px solid #eee;
        }
        .tx tbody td:last-child { text-align: right; font-weight: 600; }

        /* ── Section heading ── */
        .sec-title {
            font-size: 9px;
            font-weight: 700;
            color: #333;
            margin-bottom: 6px;
        }

        /* ── Notes ── */
        .notes-box {
            border-left: 3px solid #ddd;
            padding: 6px 12px;
            margin-bottom: 16px;
            font-size: 9px;
            color: #555;
        }
        .notes-box strong {
            display: block;
            font-size: 8px;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        /* ── Footer ── */
        .footer {
            border-top: 1px solid #ccc;
            padding-top: 12px;
            margin-top: 16px;
        }
        .footer-row { width: 100%; }
        .footer-row td {
            font-size: 8px;
            color: #888;
            line-height: 1.5;
            vertical-align: top;
        }
        .footer-center {
            text-align: center;
            font-size: 7px;
            color: #aaa;
            padding-top: 8px;
        }

        /* ── Watermark ── */
        .watermark {
            position: fixed;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 80px;
            font-weight: 900;
            opacity: 0.03;
            color: #1a7431;
            pointer-events: none;
        }
    </style>
</head>
<body>
<div class="page">

    @if(strtolower($invoice['status']) === 'paid')
        <div class="watermark">PAID</div>
    @endif

    {{-- ── HEADER ── --}}
    <table class="header">
        <tr>
            <td style="width: 60%;">
                <div class="company-name">{{ $companyName }}</div>
                <div class="company-sub">Cloud Infrastructure &amp; Hosting</div>
            </td>
            <td style="width: 40%;">
                <div class="inv-label">Invoice</div>
            </td>
        </tr>
    </table>

    {{-- ── ADDRESSES + DETAILS ── --}}
    <table class="meta">
        <tr>
            {{-- Left: From + To --}}
            <td style="width: 55%;">
                {{-- Sender line --}}
                <div class="sender-line">
                    {{ $companyName }}
                    @if(!empty($companyDetails['address1'])), {{ $companyDetails['address1'] }}@endif
                    @if(!empty($companyDetails['city'])), {{ $companyDetails['city'] }}@endif
                    @if(!empty($companyDetails['country'])), {{ $companyDetails['country'] }}@endif
                </div>

                {{-- Bill to --}}
                <div class="address">
                    <strong>{{ $clientDetails['name'] }}</strong><br>
                    @if(!empty($clientDetails['company'])){{ $clientDetails['company'] }}<br>@endif
                    @if(!empty($clientDetails['address1'])){{ $clientDetails['address1'] }}<br>@endif
                    @if(!empty($clientDetails['address2'])){{ $clientDetails['address2'] }}<br>@endif
                    @php
                        $city = collect([$clientDetails['city'] ?? '', $clientDetails['state'] ?? '', $clientDetails['postcode'] ?? ''])->filter()->implode(', ');
                    @endphp
                    @if($city){{ $city }}<br>@endif
                    @if(!empty($clientDetails['country'])){{ $clientDetails['country'] }}@endif
                </div>
            </td>

            {{-- Right: Invoice details --}}
            <td style="width: 45%;">
                <table class="details" style="float: right;">
                    <tr>
                        <td>Invoice No.</td>
                        <td style="font-size: 11px; color: #111;">{{ $invoice['invoicenum'] ?: $invoice['invoiceid'] }}</td>
                    </tr>
                    <tr>
                        <td>Date</td>
                        <td>{{ \Carbon\Carbon::parse($invoice['date'])->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td>Due Date</td>
                        <td>{{ \Carbon\Carbon::parse($invoice['duedate'])->format('d M Y') }}</td>
                    </tr>
                    @if(strtolower($invoice['status']) === 'paid' && !empty($invoice['datepaid']) && $invoice['datepaid'] !== '0000-00-00 00:00:00')
                    <tr>
                        <td>Paid On</td>
                        <td style="color: #1a7431;">{{ \Carbon\Carbon::parse($invoice['datepaid'])->format('d M Y') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>Payment Method</td>
                        <td>{{ $paymentMethodName }}</td>
                    </tr>
                    @if(!empty($currencyCode))
                    <tr>
                        <td>Currency</td>
                        <td>{{ $currencyCode }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>Status</td>
                        <td>
                            @php
                                $sl = strtolower($invoice['status']);
                                $bc = match($sl) { 'paid'=>'b-paid', 'unpaid'=>'b-unpaid', 'overdue'=>'b-overdue', 'cancelled'=>'b-cancelled', 'refunded'=>'b-refunded', default=>'b-unpaid' };
                            @endphp
                            <span class="badge {{ $bc }}">{{ $invoice['status'] }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ── LINE ITEMS ── --}}
    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @php
                $raw = $invoice['items']['item'] ?? [];
                $lines = isset($raw['id']) ? [$raw] : $raw;
            @endphp
            @forelse($lines as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item['description'] }}</td>
                <td>{{ number_format((float)($item['amount'] ?? 0), 2) }}{{ $currencySuffix }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="3" style="text-align: center; color: #999; padding: 16px;">No line items</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── TOTALS ── --}}
    <div class="totals-wrap">
        <table style="width: 100%;">
            <tr>
                <td style="width: 60%;"></td>
                <td style="width: 40%;">
                    <table class="totals">
                        <tr>
                            <td>Subtotal</td>
                            <td>{{ number_format((float)($invoice['subtotal'] ?? 0), 2) }}{{ $currencySuffix }}</td>
                        </tr>
                        @if((float)($invoice['tax'] ?? 0) > 0)
                        <tr>
                            <td>Tax ({{ $invoice['taxrate'] ?? 0 }}%)</td>
                            <td>{{ number_format((float)$invoice['tax'], 2) }}{{ $currencySuffix }}</td>
                        </tr>
                        @endif
                        @if((float)($invoice['tax2'] ?? 0) > 0)
                        <tr>
                            <td>Tax 2 ({{ $invoice['taxrate2'] ?? 0 }}%)</td>
                            <td>{{ number_format((float)$invoice['tax2'], 2) }}{{ $currencySuffix }}</td>
                        </tr>
                        @endif
                        @if((float)($invoice['credit'] ?? 0) > 0)
                        <tr>
                            <td>Credit Applied</td>
                            <td style="color: #1a7431;">-{{ number_format((float)$invoice['credit'], 2) }}{{ $currencySuffix }}</td>
                        </tr>
                        @endif
                        <tr class="t-final">
                            <td>Total</td>
                            <td>{{ number_format((float)($invoice['total'] ?? 0), 2) }}{{ $currencySuffix }}</td>
                        </tr>
                        @if((float)($invoice['balance'] ?? 0) > 0 && strtolower($invoice['status']) !== 'paid')
                        <tr class="t-balance">
                            <td>Balance Due</td>
                            <td>{{ number_format((float)$invoice['balance'], 2) }}{{ $currencySuffix }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── PAID BANNER ── --}}
    @if(strtolower($invoice['status']) === 'paid')
    <div class="paid-box">
        <strong>Payment Received</strong>
        <span>
            This invoice has been paid in full.
            @if(!empty($invoice['datepaid']) && $invoice['datepaid'] !== '0000-00-00 00:00:00')
                Payment received on {{ \Carbon\Carbon::parse($invoice['datepaid'])->format('d M Y, g:i A') }}.
            @endif
        </span>
    </div>
    @endif

    {{-- ── TRANSACTIONS ── --}}
    @php
        $rawTx = $invoice['transactions']['transaction'] ?? [];
        if (!empty($rawTx) && !is_array(reset($rawTx))) { $rawTx = [$rawTx]; }
        $txs = array_filter($rawTx, fn($t) => is_array($t) && isset($t['id']));
    @endphp
    @if(count($txs) > 0)
        <div class="sec-title">Transactions</div>
        <table class="tx">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Gateway</th>
                    <th>Transaction ID</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($txs as $tx)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($tx['date'] ?? '')->format('d M Y') }}</td>
                    <td>{{ ucfirst($tx['gateway'] ?? '-') }}</td>
                    <td style="font-size: 8px;">{{ $tx['transid'] ?? '-' }}</td>
                    <td>{{ number_format((float)($tx['amount'] ?? 0), 2) }}{{ $currencySuffix }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ── NOTES ── --}}
    @if(!empty($invoice['notes']))
    <div class="notes-box">
        <strong>Notes</strong>
        {!! nl2br(e($invoice['notes'])) !!}
    </div>
    @endif

    {{-- ── FOOTER ── --}}
    <div class="footer">
        <table class="footer-row">
            <tr>
                <td style="width: 33%;">
                    <strong style="color: #555;">{{ $companyName }}</strong><br>
                    @if(!empty($companyDetails['address1'])){{ $companyDetails['address1'] }}<br>@endif
                    @php $fCity = collect([$companyDetails['city'] ?? '', $companyDetails['state'] ?? '', $companyDetails['postcode'] ?? ''])->filter()->implode(', '); @endphp
                    @if($fCity){{ $fCity }}<br>@endif
                    @if(!empty($companyDetails['country'])){{ $companyDetails['country'] }}@endif
                </td>
                <td style="width: 34%; text-align: center;">
                    @if(!empty($companyDetails['email'])){{ $companyDetails['email'] }}<br>@endif
                    @if(!empty($companyDetails['phone'])){{ $companyDetails['phone'] }}<br>@endif
                    orcus.one
                </td>
                <td style="width: 33%; text-align: right;">
                    @if(!empty($companyDetails['taxId']))Tax ID: {{ $companyDetails['taxId'] }}<br>@endif
                </td>
            </tr>
        </table>
        <div class="footer-center">
            This is a computer-generated invoice. No signature is required.
        </div>
    </div>

</div>
</body>
</html>
