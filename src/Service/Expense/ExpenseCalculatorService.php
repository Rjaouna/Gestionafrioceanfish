<?php

namespace App\Service\Expense;

use App\Entity\Expense;

final readonly class ExpenseCalculatorService
{
    public function applyTotals(Expense $expense): void
    {
        $amountHt = $this->normalize($expense->getAmountHt());
        $vatRate = $this->normalize($expense->getVatRate());
        $vatAmount = round($amountHt * ($vatRate / 100), 2);
        $amountTtc = round($amountHt + $vatAmount, 2);

        $expense
            ->setAmountHt($amountHt)
            ->setVatRate($vatRate)
            ->setVatAmount($vatAmount)
            ->setAmountTtc($amountTtc);
    }

    /** @return array{amountHt: string, vatRate: string, vatAmount: string, amountTtc: string} */
    public function preview(int|float|string|null $amountHt, int|float|string|null $vatRate): array
    {
        $amount = $this->normalize($amountHt);
        $rate = $this->normalize($vatRate);
        $vat = round($amount * ($rate / 100), 2);

        return [
            'amountHt' => $this->format($amount),
            'vatRate' => $this->format($rate),
            'vatAmount' => $this->format($vat),
            'amountTtc' => $this->format($amount + $vat),
        ];
    }

    private function normalize(int|float|string|null $value): float
    {
        $normalized = str_replace(',', '.', trim((string) ($value ?? '0')));

        return is_numeric($normalized) ? max(0, (float) $normalized) : 0.0;
    }

    private function format(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
