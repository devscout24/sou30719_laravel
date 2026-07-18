@php
    $txStatusColors = [
        'paid'     => 'success',
        'pending'  => 'warning',
        'failed'   => 'danger',
        'refunded' => 'secondary',
    ];
    $txColor = $txStatusColors[$transaction->status] ?? 'secondary';
    $txUser = $transaction->user;
    $txAvatar = $txUser
        ? asset($txUser->avatar && $txUser->avatar !== 'user.png' ? $txUser->avatar : 'admin.png')
        : asset('admin.png');
    $txSubject = $transaction->subscription ? 'Subscription' : $transaction->contextLabel();
@endphp

<tr>
    <td><input type="checkbox" class="form-check-input row-checkbox" value="{{ $transaction->id }}"></td>
    <td>#{{ $transaction->id }}</td>
    <td>{{ optional($transaction->created_at)->format('d M Y, g:ia') ?? '—' }}</td>
    <td>{{ $txSubject }}</td>
    <td>
        <div class="d-flex align-items-center gap-2">
            <img src="{{ $txAvatar }}" class="rounded-circle avatar-xs" alt="avatar">
            @if ($txUser)
                <a href="{{ route('admin.user.show', $txUser->id) }}" class="text-body">{{ $txUser->name }}</a>
            @else
                <span>Unknown</span>
            @endif
        </div>
    </td>
    <td>{{ $transaction->contextLabel() }}</td>
    <td>{{ '$' . number_format($transaction->amount, 0) }}</td>
    <td>{{ '$' . number_format($transaction->tax, 0) }}</td>
    <td><span class="badge bg-{{ $txColor }}-subtle text-{{ $txColor }}">{{ $transaction->statusLabel() }}</span></td>
    <td class="text-center">
        <div class="dropdown">
            <a href="#" class="btn btn-default btn-icon btn-sm" data-bs-toggle="dropdown">
                <i class="ti ti-dots-vertical fs-lg"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="{{ route('admin.transactions.show', $transaction->id) }}">
                        <i class="ti ti-eye fs-sm me-1 align-middle"></i> View Details
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('admin.transactions.invoice', $transaction->id) }}">
                        <i class="ti ti-file-invoice fs-sm me-1 align-middle"></i> View Invoice
                    </a>
                </li>
            </ul>
        </div>
    </td>
</tr>
