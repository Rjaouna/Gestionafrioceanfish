<?php

namespace App\Tests\Service;

use App\Entity\CashFundTransaction;
use App\Entity\Expense;
use App\Entity\User;
use App\Repository\CashFundTransactionRepository;
use App\Service\Expense\CashFundService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class CashFundServiceTest extends TestCase
{
    public function testDeductPaidExpenseBlocksWhenBalanceIsInsufficient(): void
    {
        $repository = $this->createStub(CashFundTransactionRepository::class);
        $repository->method('findExpensePayment')->willReturn(null);
        $repository->method('balance')->willReturn(50.0);

        $service = new CashFundService($repository, $this->createStub(EntityManagerInterface::class));

        $expense = (new Expense())
            ->setReference('DEP-TEST')
            ->setTitle('Test')
            ->setSupplierName('Fournisseur')
            ->setAmountTtc(100);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Solde cagnotte insuffisant');

        $service->deductPaidExpense($expense, new User());
    }

    public function testDeductPaidExpenseCreatesNegativeMovementOnce(): void
    {
        $repository = $this->createStub(CashFundTransactionRepository::class);
        $repository->method('findExpensePayment')->willReturn(null);
        $repository->method('balance')->willReturn(250.0);
        $repository->method('nextReferenceNumber')->willReturn(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(CashFundTransaction::class));

        $service = new CashFundService($repository, $entityManager);

        $expense = (new Expense())
            ->setReference('DEP-TEST')
            ->setTitle('Test')
            ->setSupplierName('Fournisseur')
            ->setPaymentMethod('card')
            ->setAmountTtc(100);

        $transaction = $service->deductPaidExpense($expense, new User());

        self::assertInstanceOf(CashFundTransaction::class, $transaction);
        self::assertSame('-100.00', $transaction->getAmount());
        self::assertSame(CashFundTransaction::PAYMENT_METHOD_OTHER, $transaction->getPaymentMethod());
        self::assertSame($expense, $transaction->getExpense());
    }
}
