@extends('backend.master')

@section('page_title', 'Invoice')

@section('content')

    <div class="d-flex align-items-center justify-content-between mb-3">
        <a href="{{ route('admin.transactions.show', $transaction) }}"
            class="d-inline-flex align-items-center gap-1 text-muted">
            <i class="ti ti-arrow-left fs-lg"></i> Invoice
        </a>

        <a href="{{ route('admin.transactions.invoice.download', $transaction) }}" class="btn btn-dark">
            <i class="ti ti-download fs-sm me-2"></i> Download
        </a>
    </div>

    <div class="card">
        <div class="card-body p-4">
            @include('backend.partial.invoice-content', ['transaction' => $transaction, 'company' => $company])
        </div>
    </div>

@endsection
