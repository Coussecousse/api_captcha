<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    
    #[ORM\Column(length: 255)]
    private ?string $public_key = null;

    #[ORM\Column(length: 255)]
    private ?string $private_key = null;

    public function __construct() {
        $this->public_key = bin2hex(random_bytes(32));
        $this->private_key = password_hash($this->public_key, PASSWORD_DEFAULT);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicKey(): ?string
    {
        return $this->public_key;
    }

    public function setPublicKey(string $key): static
    {
        $this->public_key = $key;

        return $this;
    }

    public function getPrivateKey(): ?string
    {
        return $this->private_key;
    }

    public function setPrivateKey(): static
    {
        $this->private_key = password_hash($this->public_key, PASSWORD_DEFAULT);;

        return $this;
    }
}
