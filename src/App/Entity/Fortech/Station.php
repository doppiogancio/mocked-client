<?php

namespace App\Entity\Fortech;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'stazioni')]
class Station
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $brinId = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBrinId(): int
    {
        return $this->brinId;
    }

    public function setBrinId(int $brinId): self
    {
        $this->brinId = $brinId;

        return $this;
    }
}
