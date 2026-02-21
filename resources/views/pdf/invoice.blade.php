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
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 13px;
            color: #1a1a2e;
            line-height: 1.5;
            background: #fff;
        }

        /* ── Page Container ── */
        .invoice-wrap {
            padding: 40px 45px;
            position: relative;
        }

        /* ── Top accent bar ── */
        .accent-bar {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6, #a78bfa);
        }

        /* ── Header ── */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 35px;
        }
        .header-left {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        .header-right {
            display: table-cell;
            vertical-align: top;
            width: 50%;
            text-align: right;
        }
        .company-name {
            font-size: 28px;
            font-weight: 700;
            color: #6366f1;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }
        .company-tagline {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .invoice-title {
            font-size: 36px;
            font-weight: 800;
            color: #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 6px;
        }

        /* ── Invoice Meta Grid ── */
        .meta-grid {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .meta-left {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        .meta-right {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        .meta-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 18px 20px;
        }
        .meta-card-right {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 18px 20px;
            margin-left: 20px;
        }
        .meta-label {
            font-size: 10px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        .meta-value {
            font-size: 13px;
            color: #1e293b;
            font-weight: 500;
            line-height: 1.6;
        }
        .meta-value strong {
            color: #0f172a;
            font-weight: 600;
        }

        /* ── Status Badge ── */
        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .status-paid {
            background: #dcfce7;
            color: #166534;
        }
        .status-unpaid {
            background: #fef3c7;
            color: #92400e;
        }
        .status-overdue {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-cancelled {
            background: #f1f5f9;
            color: #64748b;
        }
        .status-refunded {
            background: #ede9fe;
            color: #5b21b6;
        }

        /* ── Items Table ── */
        .items-section {
            margin-bottom: 25px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 8px;
            overflow: hidden;
        }
        .items-table thead th {
            background: linear-gradient(135deg, #6366f1, #7c3aed);
            color: #fff;
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            text-align: left;
        }
        .items-table thead th:last-child {
            text-align: right;
        }
        .items-table tbody td {
            padding: 14px 16px;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        .items-table tbody tr:nth-child(even) td {
            background: #fafbfc;
        }
        .items-table tbody tr:last-child td {
            border-bottom: none;
        }
        .items-table tbody td:first-child {
            font-weight: 500;
            color: #1e293b;
        }
        .items-table tbody td:last-child {
            text-align: right;
            font-weight: 600;
            color: #1e293b;
        }
        .item-number {
            display: inline-block;
            width: 22px;
            height: 22px;
            background: #eef2ff;
            color: #6366f1;
            border-radius: 50%;
            text-align: center;
            line-height: 22px;
            font-size: 10px;
            font-weight: 700;
            margin-right: 10px;
        }

        /* ── Totals ── */
        .totals-row {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .totals-spacer {
            display: table-cell;
            width: 55%;
        }
        .totals-box {
            display: table-cell;
            width: 45%;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table tr td {
            padding: 8px 16px;
            font-size: 13px;
        }
        .totals-table tr td:first-child {
            color: #64748b;
            font-weight: 500;
        }
        .totals-table tr td:last-child {
            text-align: right;
            color: #1e293b;
            font-weight: 600;
        }
        .totals-table tr.divider td {
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
        }
        .totals-table tr.total-row td {
            padding: 14px 16px;
            font-size: 16px;
            font-weight: 700;
            background: linear-gradient(135deg, #6366f1, #7c3aed);
            color: #fff !important;
        }
        .totals-table tr.total-row td:first-child {
            border-radius: 8px 0 0 8px;
            color: #fff !important;
        }
        .totals-table tr.total-row td:last-child {
            border-radius: 0 8px 8px 0;
            color: #fff !important;
        }
        .totals-table tr.balance-row td {
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 700;
        }
        .totals-table tr.balance-row td:last-child {
            color: #dc2626;
        }

        /* ── Transactions ── */
        .transactions-section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #6366f1;
            display: inline-block;
        }
        .tx-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }
        .tx-table thead th {
            background: #f8fafc;
            color: #475569;
            padding: 10px 14px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .tx-table tbody td {
            padding: 10px 14px;
            font-size: 12px;
            color: #475569;
            border-bottom: 1px solid #f1f5f9;
        }
        .tx-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* ── Notes ── */
        .notes-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
            border-radius: 6px;
            padding: 14px 18px;
            margin-bottom: 25px;
        }
        .notes-label {
            font-size: 11px;
            font-weight: 700;
            color: #92400e;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 4px;
        }
        .notes-text {
            font-size: 12px;
            color: #78350f;
            line-height: 1.6;
        }

        /* ── Footer ── */
        .footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
            text-align: center;
        }
        .footer-brand {
            font-size: 16px;
            font-weight: 700;
            color: #6366f1;
            margin-bottom: 4px;
        }
        .footer-text {
            font-size: 11px;
            color: #94a3b8;
            line-height: 1.6;
        }
        .footer-divider {
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #6366f1, #a78bfa);
            border-radius: 2px;
            margin: 12px auto;
        }

        /* ── Paid Watermark ── */
        .watermark {
            position: fixed;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 100px;
            font-weight: 900;
            letter-spacing: 12px;
            opacity: 0.04;
            color: #166534;
            z-index: 0;
            pointer-events: none;
        }

        /* ── Due date highlight ── */
        .due-highlight {
            display: inline-block;
            padding: 2px 10px;
            background: #fef3c7;
            color: #92400e;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
        }
        .due-overdue {
            background: #fee2e2;
            color: #991b1b;
        }

        /* ── Payment Info ── */
        .payment-info {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 25px;
        }
        .payment-info-title {
            font-size: 11px;
            font-weight: 700;
            color: #166534;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 6px;
        }
        .payment-info-text {
            font-size: 12px;
            color: #15803d;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="invoice-wrap">
        {{-- Accent Bar --}}
        <div class="accent-bar"></div>

        {{-- Paid Watermark --}}
        @if(strtolower($invoice['status']) === 'paid')
            <div class="watermark">PAID</div>
        @endif

        {{-- ═══════════════ HEADER ═══════════════ --}}
        <div class="header">
            <div class="header-left">
                <div class="company-name">{{ $companyName }}</div>
                <div class="company-tagline">Cloud Infrastructure & Hosting</div>
            </div>
            <div class="header-right">
                <div class="invoice-title">INVOICE</div>
                <div style="margin-top: 4px;">
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
                    <span class="status-badge {{ $statusClass }}">{{ $invoice['status'] }}</span>
                </div>
            </div>
        </div>

        {{-- ═══════════════ INVOICE META ═══════════════ --}}
        <div class="meta-grid">
            <div class="meta-left">
                <div class="meta-card">
                    <div class="meta-label">Billed To</div>
                    <div class="meta-value">
                        <strong>{{ $clientDetails['name'] }}</strong><br>
                        @if(!empty($clientDetails['company']))
                            {{ $clientDetails['company'] }}<br>
                        @endif
                        {{ $clientDetails['email'] }}<br>
                        @if(!empty($clientDetails['address1']))
                            {{ $clientDetails['address1'] }}<br>
                        @endif
                        @if(!empty($clientDetails['address2']))
                            {{ $clientDetails['address2'] }}<br>
                        @endif
                        @php
                            $cityLine = collect([
                                $clientDetails['city'] ?? '',
                                $clientDetails['state'] ?? '',
                                $clientDetails['postcode'] ?? '',
                            ])->filter()->implode(', ');
                        @endphp
                        @if($cityLine)
                            {{ $cityLine }}<br>
                        @endif
                        @if(!empty($clientDetails['country']))
                            {{ $clientDetails['country'] }}
                        @endif
                    </div>
                </div>
            </div>
            <div class="meta-right">
                <div class="meta-card-right">
                    <div class="meta-label">Invoice Details</div>
                    <div class="meta-value">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 3px 0; color: #64748b; font-size: 12px;">Invoice #</td>
                                <td style="padding: 3px 0; text-align: right; font-weight: 700; color: #6366f1; font-size: 14px;">{{ $invoice['invoicenum'] ?: $invoice['invoiceid'] }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 3px 0; color: #64748b; font-size: 12px;">Date Issued</td>
                                <td style="padding: 3px 0; text-align: right; font-weight: 600;">{{ \Carbon\Carbon::parse($invoice['date'])->format('M d, Y') }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 3px 0; color: #64748b; font-size: 12px;">Due Date</td>
                                <td style="padding: 3px 0; text-align: right;">
                                    @if(strtolower($invoice['status']) === 'overdue')
                                        <span class="due-highlight due-overdue">{{ \Carbon\Carbon::parse($invoice['duedate'])->format('M d, Y') }}</span>
                                    @elseif(strtolower($invoice['status']) === 'unpaid')
                                        <span class="due-highlight">{{ \Carbon\Carbon::parse($invoice['duedate'])->format('M d, Y') }}</span>
                                    @else
                                        <span style="font-weight: 600;">{{ \Carbon\Carbon::parse($invoice['duedate'])->format('M d, Y') }}</span>
                                    @endif
                                </td>
                            </tr>
                            @if(strtolower($invoice['status']) === 'paid' && !empty($invoice['datepaid']) && $invoice['datepaid'] !== '0000-00-00 00:00:00')
                                <tr>
                                    <td style="padding: 3px 0; color: #64748b; font-size: 12px;">Date Paid</td>
                                    <td style="padding: 3px 0; text-align: right; font-weight: 600; color: #166534;">{{ \Carbon\Carbon::parse($invoice['datepaid'])->format('M d, Y') }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td style="padding: 3px 0; color: #64748b; font-size: 12px;">Payment Method</td>
                                <td style="padding: 3px 0; text-align: right; font-weight: 500;">{{ $paymentMethodName }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════ LINE ITEMS ═══════════════ --}}
        <div class="items-section">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 6%;">#</th>
                        <th style="width: 70%;">Description</th>
                        <th style="width: 24%; text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $rawItems = $invoice['items']['item'] ?? [];
                        $lineItems = isset($rawItems['id']) ? [$rawItems] : $rawItems;
                    @endphp
                    @forelse($lineItems as $i => $item)
                        <tr>
                            <td>
                                <span class="item-number">{{ $i + 1 }}</span>
                            </td>
                            <td>{{ $item['description'] }}</td>
                            <td>{{ $currencyPrefix }}{{ number_format((float)($item['amount'] ?? 0), 2) }}{{ $currencySuffix }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="text-align: center; color: #94a3b8; padding: 30px;">No line items</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ═══════════════ TOTALS ═══════════════ --}}
        <div class="totals-row">
            <div class="totals-spacer"></div>
            <div class="totals-box">
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
                    <tr class="divider total-row">
                        <td>Total</td>
                        <td>{{ $currencyPrefix }}{{ number_format((float)($invoice['total'] ?? 0), 2) }}{{ $currencySuffix }}</td>
                    </tr>
                    @if((float)($invoice['balance'] ?? 0) > 0 && strtolower($invoice['status']) !== 'paid')
                        <tr class="balance-row">
                            <td>Balance Due</td>
                            <td>{{ $currencyPrefix }}{{ number_format((float)$invoice['balance'], 2) }}{{ $currencySuffix }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>

        {{-- ═══════════════ PAID CONFIRMATION ═══════════════ --}}
        @if(strtolower($invoice['status']) === 'paid')
            <div class="payment-info">
                <div class="payment-info-title">✓ Payment Received</div>
                <div class="payment-info-text">
                    This invoice has been paid in full.
                    @if(!empty($invoice['datepaid']) && $invoice['datepaid'] !== '0000-00-00 00:00:00')
                        Payment received on {{ \Carbon\Carbon::parse($invoice['datepaid'])->format('F d, Y \a\t g:i A') }}.
                    @endif
                    Thank you for your prompt payment!
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
            <div class="transactions-section">
                <div class="section-title">Payment Transactions</div>
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
                                <td>{{ ucfirst($tx['gateway'] ?? '—') }}</td>
                                <td style="font-family: monospace; font-size: 11px;">{{ $tx['transid'] ?? '—' }}</td>
                                <td style="text-align: right; font-weight: 600;">{{ $currencyPrefix }}{{ number_format((float)($tx['amount'] ?? 0), 2) }}{{ $currencySuffix }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- ═══════════════ NOTES ═══════════════ --}}
        @if(!empty($invoice['notes']))
            <div class="notes-box">
                <div class="notes-label">📝 Notes</div>
                <div class="notes-text">{!! nl2br(e($invoice['notes'])) !!}</div>
            </div>
        @endif

        {{-- ═══════════════ FOOTER ═══════════════ --}}
        <div class="footer">
            <div class="footer-divider"></div>
            <div class="footer-brand">{{ $companyName }}</div>
            <div class="footer-text">
                Cloud Infrastructure & Hosting Solutions<br>
                <span style="color: #6366f1;">support@orcustech.com</span> &nbsp;•&nbsp; <span style="color: #6366f1;">orcus.one</span>
            </div>
            <div style="margin-top: 12px; font-size: 10px; color: #cbd5e1;">
                This is a computer-generated invoice. No signature is required.
            </div>
        </div>
    </div>
</body>
</html>
