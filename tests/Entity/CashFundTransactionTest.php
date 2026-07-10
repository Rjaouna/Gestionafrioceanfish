<?php

namespace App\Tests\Entity;

use App\Entity\CashFundTransaction;
use PHPUnit\Framework\TestCase;

final class CashFundTransactionTest extends TestCase
{
    public function testFundingIsPositiveInflow(): void
    {
        $transaction = (new CashFundTransaction())
            ->setType(CashFundTransaction::TYPE_FUNDING)
            ->setAmount('1500,50');

        self::assertSame('1500.50', $transaction->getAmount());
        self::assertTrue($transaction->isInflow());
        self::assertSame(1500.50, $transaction->absoluteAmountValue());
        self::assertSame('Alimentation', $transaction->getTypeLabel());
    }

    public function testExpensePaymentIsNegativeOutflow(): void
    {
        $transaction = (new CashFundTransaction())
            ->setType(CashFundTransaction::TYPE_EXPENSE_PAYMENT)
            ->setAmount('-240.25');

        self::assertFalse($transaction->isInflow());
        self::assertSame(240.25, $transaction->absoluteAmountValue());
        self::assertSame('Depense payee', $transaction->getTypeLabel());
    }
}
