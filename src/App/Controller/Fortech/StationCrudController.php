<?php

namespace App\Controller\Fortech;

use App\Entity\Fortech\Station;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class StationCrudController
{
    public function create(array $data, EntityManagerInterface $entityManager): Station
    {
        if (!array_key_exists('brinId', $data)) {
            throw new InvalidArgumentException('The brinId field is required when creating a station.');
        }

        $brinId = (int) $data['brinId'];

        $station = new Station();
        $station->setBrinId($brinId);

        $entityManager->persist($station);
        $entityManager->flush();

        return $station;
    }
}
