<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'stazioni')]
class Station
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'brin_id', type: 'integer', options: ['default' => 0])]
    private int $brinId = 0;

    public function __construct(int $brinId)
    {
        $this->brinId = $brinId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBrinId(): int
    {
        return $this->brinId;
    }

    public function setBrinId(int $brinId): void
    {
        $this->brinId = $brinId;
    }
}
