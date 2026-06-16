<?php

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\ContactShare;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ContactShare> */
class ContactShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactShare::class);
    }

    public function findFor(Contact $contact, User $user): ?ContactShare
    {
        return $this->findOneBy(['contact' => $contact, 'user' => $user]);
    }
}
