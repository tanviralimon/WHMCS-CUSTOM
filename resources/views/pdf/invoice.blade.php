<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice #{{ $invoice['invoicenum'] ?: $invoice['invoiceid'] }}</title>
    <style>
        /* ── Reset & Base ── */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.5;
            background: #fff;
        }

        .page {
            padding: 50px;
        }

        /* ── Header ── */
        .header-table {
            width: 100%;
            margin-bottom: 40px;
        }
        .header-table td {
            vertical-align: top;
        }
        .company-name {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 3px;
        }
        .company-info {
            font-size: 11px;
            color: #666;
            line-height: 1.7;
        }
        .invoice-label {
            font-size: 32px;
            font-weight: 700;
            color: #2563eb;
            text-align: right;
            letter-spacing: 1px;
        }

        /* ── Divider ── */
        .divider {
            border: none;
            border-top: 2px solid #2563eb;
            margin-bottom: 30px;
        }

        /* ── Invoice Meta ── */
        .meta-table {
            width: 100%;
            margin-bottom: 35px;
        }
        .meta-table > tbody > tr > td {
            vertical-align: top;
        }
        .meta-heading {
            font-size: 10px;
            font-weight: 700;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .meta-content {
            font-size: 12px;
            color: #333;
            line-height: 1.8;
        }
        .meta-content strong {
            color: #111;
        }

        /* ── Invoice Details Box ── */
        .details-table {
            border-collapse: collapse;
            float: right;
        }
        .details-table td {
            padding: 5px 0;
            font-size: 12px;
        }
        .details-table td:first-child {
            color: #666;
            padding-right: 20px;
        }
        .details-table td:last-child {
            text-align: right;
            font-weight: 600;
            color: #111;
        }
        .invoice-number {
            color: #2563eb !important;
            font-size: 14px !important;
        }

        /* ── Status ── */
        .status {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-unpaid { background: #fef3c7; color: #92400e; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        .status-cancelled { background: #f3f4f6; color: #6b7280; }
        .status-refunded { background: #ede9fe; color: #5b21b6; }

        /* ── Line Items Table ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        .items-table thead th {
            background: #f8f9fa;
            border-top: 2px solid #2563eb;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 12px;
            font-size: 10px;
            font-weight: 700;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
        }
        .items-table thead th:last-child {
            text-align: right;
        }
        .items-table tbody td {
            padding: 12px;
            font-size: 12px;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        .items-table tbody td:first-child {
            color: #888;
            width: 5%;
        }
        .items-table tbody td:last-child {
            text-align: right;
            font-weight: 600;
        }

        /* ── Totals ── */
        .totals-wrapper {
            width: 100%;
            margin-bottom: 30px;
        }
        .totals-outer {
            width: 100%;
        }
        .totals-outer td.spacer-cell {
            width: 55%;
        }
        .totals-outer td.totals-cell {
            width: 45%;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 7px 12px;
            font-size: 12px;
        }
        .totals-table td:first-child {
            color: #666;
        }
        .totals-table td:last-child {
            text-align: right;
            font-weight: 600;
            color: #111;
        }
        .total-final td {
            border-top: 2px solid #2563eb;
            padding-top: 10px;
            font-size: 15px !important;
            font-weight: 700 !important;
        }
        .total-final td:first-child {
            color: #111 !important;
        }
        .total-final td:last-child {
            color: #2563eb !important;
        }
        .balance-due td {
            padding-top: 8px;
            font-size: 13px !important;
        }
        .balance-due td:last-child {
            color: #dc2626 !important;
            font-weight: 700 !important;
        }

        /* ── Paid Banner ── */
        .paid-banner {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 14px 18px;
            margin-bottom: 25px;
        }
        .paid-banner-title {
            font-size: 13px;
            font-weight: 700;
            color: #166534;
            margin-bottom: 3px;
        }
        .paid-banner-text {
            font-size: 11px;
            color: #15803d;
        }

        /* ── Transactions ── */
        .tx-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .tx-table thead th {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 8px 12px;
            font-size: 10px;
            font-weight: 700;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
        }
        .tx-table thead th:last-child { text-align: right; }
        .tx-table tbody td {
            padding: 8px 12px;
            font-size: 11px;
            color: #555;
            border-bottom: 1px solid #f0f0f0;
        }
        .tx-table tbody td:last-child {
            text-align: right;
            font-weight: 600;
        }

        /* ── Section Heading ── */
        .section-heading {
            font-size: 12px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #ddd;
        }

        /* ── Notes ── */
        .notes {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 6px;
            padding: 14px 18px;
            margin-bottom: 25px;
        }
        .notes-title {
            font-size: 10px;
            font-weight: 700;
            color: #92400e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .notes-body {
            font-size: 11px;
            color: #78350f;
            line-height: 1.6;
        }

        /* ── Footer ── */
        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 18px;
            text-align: center;
            margin-top: 10px;
        }
        .footer-company {
            font-size: 13px;
            font-weight: 700;
            color: #111;
            margin-bottom: 3px;
        }
        .footer-text {
            font-size: 10px;
            color: #999;
            line-height: 1.6;
        }

        /* ── Watermark ── */
        .watermark {
            position: fixed;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 90px;
            font-weight: 900;
            letter-spacing: 10px;
            opacity: 0.04;
            color: #166534;
            z-index: 0;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="page">

        {{-- Paid Watermark --}}
        @if(strtolower($invoice['status']) === 'paid')
            <div class="watermark">PAID</div>
        @endif

        {{-- ═══════════════ HEADER ═══════════════ --}}
        <table class="header-table">
            <tr>
                <td style="width: 55%;">
                    <div class="company-name">{{ $companyName }}</div>
                    <div class="company-info">
                        @if(!empty($companyDetails['address1']))
                            {{ $companyDetails['address1'] }}<br>
                        @endif
                        @if(!empty($companyDetails['address2']))
                            {{ $companyDetails['address2'] }}<br>
                        @endif
                        @php
                            $hdrCity = collect([
                                $companyDetails['city'] ?? '',
                                $companyDetails['state'] ?? '',
                                $companyDetails['postcode'] ?? '',
                            ])->filter()->implode(', ');
                        @endphp
                        @if($hdrCity){{ $hdrCity }}<br>@endif
                        @if(!empty($companyDetails['country'])){{ $companyDetails['country'] }}<br>@endif
                        @if(!empty($companyDetails['phone']))Tel: {{ $companyDetails['phone'] }}<br>@endif
                        @if(!empty($companyDetails['email'])){{ $companyDetails['email'] }}@endif
                        @if(!empty($companyDetails['taxId']))<br>Tax ID: {{ $companyDetails['taxId'] }}@endif
                    </div>
                </td>
                <td style="width: 45%;">
                    <div class="invoice-label">INVOICE</div>
                </td>
            </tr>
        </table>

        <hr class="divider">

        {{-- ═══════════════ BILL TO + INVOICE DETAILS ═══════════════ --}}
        <table class="meta-table">
            <tr>
                <td style="width: 50%;">
                    <div class="meta-heading">Bill To</div>
                    <div class="meta-content">
                        <strong>{{ $clientDetails['name'] }}</strong><br>
                        @if(!empty($clientDetails['company']))
                            {{ $clientDetails['company'] }}<br>
                        @endif
                        {{ $clientDetails['email'] }}<br>
                        @if(!empty($clientDetails['address1'])){{ $clientDetails['address1'] }}<br>@endif
                        @if(!empty($clientDetails['address2'])){{ $clientDetails['address2'] }}<br>@endif
                        @php
                            $cityLine = collect([
                                $clientDetails['city'] ?? '',
                                $clientDetails['state'] ?? '',
                                $clientDetails['postcode'] ?? '',
                            ])->filter()->implode(', ');
                        @endphp
                        @if($cityLine){{ $cityLine }}<br>@endif
                        @if(!empty($clientDetails['country'])){{ $clientDetails['country'] }}@endif
                        @if(!empty($clientDetails['phone']))<br>Tel: {{ $clientDetails['phone'] }}@endif
                    </div>
                </td>
                <td style="width: 50%;">
                    <table class="details-table">
                        <tr>
                            <td>Invoice Number</td>
                            <td class="invoice-number">{{ $invoice['invoicenum'] ?: $invoice['invoiceid'] }}</td>
                        </tr>
                        <tr>
                            <td>Date Issued</td>
                            <td>{{ \Carbon\Carbon::parse($invoice['date'])->format('M d, Y') }}</td>
                        </tr>
                        <tr>
                            <td>Due Date</td>
                            <td>{{ \Carbon\Carbon::parse($invoice['duedate'])->format('M d, Y') }}</td>
                        </tr>
                        @if(strtolower($invoice['status']) === 'paid' && !empty($invoice['datepaid']) && $invoice['datepaid'] !== '0000-00-00 00:00:00')
                        <tr>
                            <td>Date Paid</td>
                            <td style="color: #166534;">{{ \Carbon\Carbon::parse($invoice['datepaid'])->format('M d, Y') }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td>Payment Method</td>
                            <td>{{ $paymentMethodName }}</td>
                        </tr>
                        @if(!empty($currencyCode))
                        <tr>
                            <td>Currency</td>
                            <td style="color: #2563eb;">{{ $currencyCode }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td>Status</td>
                            <td>
                                @php
                                    $statusLower = strtolower($invoice['status']);
                                    $statusClass = match($statusLower) {
                                        'paid' => 'status-paid',
                                        'unpaid' => 'status-unpaid',
                                        'overdue' => 'status-overdue',
                                        'cancelled' => 'status-cancelled',
                                        'refunded' => 'status-refunded',
                                        default => 'status-unpaid',
                                    };
                                @endphp
                                <span class="status {{ $statusClass }}">{{ $invoice['status'] }}</span>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ═══════════════ LINE ITEMS ═══════════════ --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $rawItems = $invoice['items']['item'] ?? [];
                    $lineItems = isset($rawItems['id']) ? [$rawItems] : $rawItems;
                @endphp
                @forelse($lineItems as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item['description'] }}</td>
                        <td>{{ $currencyPrefix }}{{ number_format((float)($item['amount'] ?? 0), 2) }}{{ $currencySuffix }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" style="text-align: center; color: #999; padding: 25px;">No line items</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- ═══════════════ TOTALS ═══════════════ --}}
        <div class="totals-wrapper">
            <table class="totals-outer">
                <tr>
                    <td class="spacer-cell"></td>
                    <td class="totals-cell">
                        <table class="totals-table">
                            <tr>
                                <td>Subtotal</td>
                                <td>{{ $currencyPrefix }}{{ number_format((float)($invoice['subtotal'] ?? 0), 2) }}{{ $currencySuffix }}</td>
                            </tr>
                            @if((float)($invoice['tax'] ?? 0) > 0)
                            <tr>
                                <td>Tax ({{ $invoice['taxrate'] ?? 0 }}%)</td>
                                <td>{{ $currencyPrefix }}{{ number_format((float)$invoice['tax'], 2) }}{{ $currencySuffix }}</td>
                            </tr>
                            @endif
                            @if((float)($invoice['tax2'] ?? 0) > 0)
                            <tr>
                                <td>Tax 2 ({{ $invoice['taxrate2'] ?? 0 }}%)</td>
                                <td>{{ $currencyPrefix }}{{ number_format((float)$invoice['tax2'], 2) }}{{ $currencySuffix }}</td>
                            </tr>
                            @endif
                            @if((float)($invoice['credit'] ?? 0) > 0)
                            <tr>
                                <td>Credit Applied</td>
                                <td style="color: #166534;">-{{ $currencyPrefix }}{{ number_format((float)$invoice['credit'], 2) }}{{ $currencySuffix }}</td>
                            </tr>
                            @endif
                            <tr class="total-final">
                                <td>Total</td>
                                <td>{{ $currencyPrefix }}{{ number_format((float)($invoice['total'] ?? 0), 2) }}{{ $currencySuffix }}</td>
                            </tr>
                            @if((float)($invoice['balance'] ?? 0) > 0 && strtolower($invoice['status']) !== 'paid')
                            <tr class="balance-due">
                                <td>Balance Due</td>
                                <td>{{ $currencyPrefix }}{{ number_format((float)$invoice['balance'], 2) }}{{ $currencySuffix }}</td>
                            </tr>
                            @endif
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        {{-- ═══════════════ PAID CONFIRMATION ═══════════════ --}}
        @if(strtolower($invoice['status']) === 'paid')
        <div class="paid-banner">
            <div class="paid-banner-title">Payment Received</div>
            <div class="paid-banner-text">
                This invoice has been paid in full.
                @if(!empty($invoice['datepaid']) && $invoice['datepaid'] !== '0000-00-00 00:00:00')
                    Payment received on {{ \Carbon\Carbon::parse($invoice['datepaid'])->format('F d, Y \a\t g:i A') }}.
                @endif
                Thank you for your payment.
            </div>
        </div>
        @endif

        {{-- ═══════════════ TRANSACTIONS ═══════════════ --}}
        @php
            $rawTx = $invoice['transactions']['transaction'] ?? [];
            if (!empty($rawTx) && !is_array(reset($rawTx))) {
                $rawTx = [$rawTx];
            }
            $transactions = array_filter($rawTx, fn($t) => is_array($t) && isset($t['id']));
        @endphp
        @if(count($transactions) > 0)
            <div class="section-heading">Transactions</div>
            <table class="tx-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Gateway</th>
                        <th>Transaction ID</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $tx)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($tx['date'] ?? '')->format('M d, Y') }}</td>
                        <td>{{ ucfirst($tx['gateway'] ?? '-') }}</td>
                        <td style="font-size: 10px;">{{ $tx['transid'] ?? '-' }}</td>
                        <td>{{ $currencyPrefix }}{{ number_format((float)($tx['amount'] ?? 0), 2) }}{{ $currencySuffix }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- ═══════════════ NOTES ═══════════════ --}}
        @if(!empty($invoice['notes']))
        <div class="notes">
            <div class="notes-title">Notes</div>
            <div class="notes-body">{!! nl2br(e($invoice['notes'])) !!}</div>
        </div>
        @endif

        {{-- ═══════════════ FOOTER ═══════════════ --}}
        <div class="footer">
            <div class="footer-company">{{ $companyName }}</div>
            <div class="footer-text">
                @if(!empty($companyDetails['email']))
                    <span style="color: #2563eb;">{{ $companyDetails['email'] }}</span>
                @endif
                @if(!empty($companyDetails['phone']))
                    &nbsp;|&nbsp; {{ $companyDetails['phone'] }}
                @endif
                @if(!empty($companyDetails['email']) || !empty($companyDetails['phone']))
                    &nbsp;|&nbsp;
                @endif
                <span style="color: #2563eb;">orcus.one</span>
                <br>
                This is a computer-generated invoice. No signature is required.
            </div>
        </div>

    </div>
</body>
</html>
