<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'formatted_id'   => $this->formattedId(),
            'plan_name'      => $this->planName(),
            'billing_cycle'  => $this->subscription?->plan?->billing_cycle,
            'amount'         => $this->amount,
            'tax'            => $this->tax,
            'total'          => $this->total,
            'currency'       => $this->currency,
            'status'         => $this->status,
            'status_label'   => $this->statusLabel(),
            'payment_method' => $this->payment_method,
            'invoice_url'    => $this->invoice_url,
            'receipt_url'    => $this->receipt_url,
            'paid_at'        => $this->paid_at?->toISOString(),
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}
