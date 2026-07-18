@php
    $invoiceUser = $transaction->user;
    $invoicePlan = optional($transaction->subscription)->plan;
    $invoiceDate = optional($transaction->paid_at ?? $transaction->created_at);
    $invoiceNumber = 'INV-' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT);
    $subtotal = (float) $transaction->amount;
    $tax = (float) $transaction->tax;
    $taxPercent = $subtotal > 0 ? round(($tax / $subtotal) * 100) : 0;
    $total = $subtotal + $tax;
@endphp
<div style="font-family: DejaVu Sans, Helvetica, Arial, sans-serif; color: #1a1a1a; font-size: 14px;">

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
        <tr>
            <td style="vertical-align: top;">
                <h1 style="font-size: 26px; margin: 0;">INVOICE</h1>
            </td>
            <td style="vertical-align: top; text-align: right;">
                <span style="font-family: cursive, sans-serif; font-size: 22px; font-weight: bold;">
                    {{ companyName() }}
                </span>
            </td>
        </tr>
    </table>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
        <tr>
            <td style="vertical-align: top; width: 50%;">
                <strong>Billed to</strong><br>
                <strong>{{ $invoiceUser->name ?? 'N/A' }}</strong><br>
                {{ $invoiceUser->address ?? '—' }}<br>
                {{ $invoiceUser->location ?? $invoiceUser->country ?? '' }}
            </td>
            <td style="vertical-align: top; width: 50%; text-align: right; color: #6c757d;">
                {{ $company->address ?? '' }}<br>
                {{ $company->email ?? '' }}<br>
                {{ $company->hotline ?? '' }}
            </td>
        </tr>
    </table>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
        <tr>
            <td style="width: 50%;">
                <strong>Invoice</strong><br>
                <span style="color: #6c757d;">#{{ $invoiceNumber }}</span>
            </td>
            <td style="width: 50%;">
                <strong>Invoice date</strong><br>
                <span style="color: #6c757d;">{{ $invoiceDate->format('d M, Y') }}</span>
            </td>
        </tr>
    </table>

    <table style="width: 100%; border-collapse: collapse; border: 1px solid #dee2e6; margin-bottom: 16px;">
        <thead>
            <tr style="background: #f8f9fa;">
                <th style="text-align: left; padding: 10px; border-bottom: 1px solid #dee2e6;">Services</th>
                <th style="text-align: left; padding: 10px; border-bottom: 1px solid #dee2e6;">Type</th>
                <th style="text-align: left; padding: 10px; border-bottom: 1px solid #dee2e6;">Billing</th>
                <th style="text-align: right; padding: 10px; border-bottom: 1px solid #dee2e6;">Price</th>
                <th style="text-align: right; padding: 10px; border-bottom: 1px solid #dee2e6;">Line total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding: 10px;">{{ $transaction->contextLabel() }}</td>
                <td style="padding: 10px; color: #6c757d;">{{ $invoicePlan->name ?? '—' }}</td>
                <td style="padding: 10px; color: #6c757d;">
                    {{ $invoicePlan ? ucfirst($invoicePlan->billing_cycle) : '—' }}</td>
                <td style="padding: 10px; text-align: right;">${{ number_format($subtotal, 2) }}</td>
                <td style="padding: 10px; text-align: right;">${{ number_format($subtotal, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
        <tr>
            <td style="width: 60%;"></td>
            <td style="width: 40%; padding: 4px 0;">Subtotal</td>
            <td style="width: 15%; padding: 4px 0; text-align: right;">${{ number_format($subtotal, 2) }}</td>
        </tr>
        <tr>
            <td></td>
            <td style="padding: 4px 0;">Tax @if ($taxPercent > 0)({{ $taxPercent }}%)@endif</td>
            <td style="padding: 4px 0; text-align: right;">${{ number_format($tax, 2) }}</td>
        </tr>
        <tr>
            <td></td>
            <td style="padding: 8px 0; border-top: 1px solid #dee2e6; font-weight: bold;">Total</td>
            <td style="padding: 8px 0; border-top: 1px solid #dee2e6; text-align: right; font-weight: bold;">
                ${{ number_format($total, 2) }}</td>
        </tr>
    </table>

    <table style="width: 100%; border-collapse: collapse; border-top: 1px solid #dee2e6; padding-top: 16px;">
        <tr>
            <td style="padding-top: 16px; text-align: left; color: #6c757d;">{{ $company->website ?? '' }}</td>
            <td style="padding-top: 16px; text-align: center; color: #6c757d;">{{ $company->hotline ?? '' }}</td>
            <td style="padding-top: 16px; text-align: right; color: #6c757d;">{{ $company->email ?? '' }}</td>
        </tr>
    </table>

</div>
