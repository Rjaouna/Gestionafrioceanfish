<?php

namespace App\Tests\Entity;

use App\Entity\InterimWorker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class InterimWorkerOptionalFieldsTest extends TestCase
{
    public function testWorkerCanBeValidatedWithOnlyNameAndFirstName(): void
    {
        $worker = (new InterimWorker())
            ->setLastName('Diallo')
            ->setFirstName('Amina')
            ->setPosition(null)
            ->setPhone(null);

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        self::assertCount(0, $validator->validateProperty($worker, 'phone'));
        self::assertCount(0, $validator->validateProperty($worker, 'lastName'));
        self::assertCount(0, $validator->validateProperty($worker, 'firstName'));
        self::assertNull($worker->getPhone());
        self::assertSame('Non renseigne', $worker->getPosition());
    }
}
