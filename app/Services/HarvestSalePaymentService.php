<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\SalePaymentStatus;
use App\Models\HarvestSale;
use App\Models\HarvestSalePayment;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HarvestSalePaymentService
{
    public function __construct(private readonly IntegrationOutboxService $outbox) {}
    public function paidAmount(HarvestSale $sale): string
    {
        $sum = $sale->payments()
            ->where('status', PaymentRecordStatus::ACTIVE)
            ->sum('amount');

        return Money::normalize($sum ?: '0');
    }

    public function outstandingAmount(HarvestSale $sale): string
    {
        $outstanding = Money::sub($sale->total_amount, $this->paidAmount($sale));

        return Money::cmp($outstanding, '0') === -1 ? Money::normalize('0') : $outstanding;
    }

    public function refreshPaymentStatus(HarvestSale $sale): SalePaymentStatus
    {
        $paid = $this->paidAmount($sale);
        $status = $this->statusFromPaid($sale->total_amount, $paid);

        if ($sale->payment_status !== $status) {
            $sale->update(['payment_status' => $status]);
            $sale->payment_status = $status;
        }

        return $status;
    }

    /**
     * @param  array{
     *     amount: float|int|string,
     *     payment_date: string,
     *     payment_method: string,
     *     reference_number?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function record(HarvestSale $sale, array $data): HarvestSalePayment
    {
        return DB::transaction(function () use ($sale, $data) {
            $locked = HarvestSale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPosted()) {
                throw new InvalidArgumentException('Hanya penjualan yang sudah diposting yang dapat menerima pembayaran.');
            }

            $amount = Money::normalize($data['amount']);
            if (Money::cmp($amount, '0') !== 1) {
                throw new InvalidArgumentException('Jumlah pembayaran harus lebih dari 0.');
            }

            $nextPaid = Money::add($this->paidAmount($locked), $amount);
            if (Money::cmp($nextPaid, $locked->total_amount) === 1) {
                throw new InvalidArgumentException('Pembayaran melebihi total penjualan.');
            }

            $payment = $locked->payments()->create([
                'amount' => $amount,
                'payment_date' => $data['payment_date'],
                'payment_method' => PaymentMethod::from($data['payment_method']),
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => PaymentRecordStatus::ACTIVE,
            ]);

            $this->refreshPaymentStatus($locked);

            $recorded = $payment->fresh() ?? $payment;
            $recorded->setRelation('sale', $locked->fresh(['plantationEntity']) ?? $locked);
            $this->outbox->recordSalePaymentReceived($recorded);

            return $recorded;
        });
    }

    public function reverse(HarvestSalePayment $payment, string $reason): HarvestSalePayment
    {
        return DB::transaction(function () use ($payment, $reason) {
            $locked = HarvestSalePayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentRecordStatus::REVERSED) {
                return $locked;
            }

            $locked->update([
                'status' => PaymentRecordStatus::REVERSED,
                'reversed_at' => now(),
                'reversed_reason' => $reason,
            ]);

            $sale = HarvestSale::query()->whereKey($locked->harvest_sale_id)->lockForUpdate()->firstOrFail();
            $this->refreshPaymentStatus($sale);

            $reversed = $locked->fresh(['sale.plantationEntity']) ?? $locked;
            $this->outbox->recordSalePaymentReversed($reversed);

            return $reversed;
        });
    }

    private function statusFromPaid(mixed $total, mixed $paid): SalePaymentStatus
    {
        if (Money::cmp($paid, '0') !== 1) {
            return SalePaymentStatus::UNPAID;
        }

        if (Money::cmp($paid, $total) === -1) {
            return SalePaymentStatus::PARTIAL;
        }

        return SalePaymentStatus::PAID;
    }
}
