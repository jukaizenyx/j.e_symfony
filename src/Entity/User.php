<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'username', columns: ['username'])]
#[ORM\UniqueConstraint(name: 'email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'username', type: Types::STRING, length: 50)]
    private string $username = '';

    #[ORM\Column(name: 'first_name', type: Types::STRING, length: 50)]
    private string $firstName = '';

    #[ORM\Column(name: 'middle_name', type: Types::STRING, length: 50, nullable: true)]
    private ?string $middleName = null;

    #[ORM\Column(name: 'last_name', type: Types::STRING, length: 50)]
    private string $lastName = '';

    #[ORM\Column(name: 'email', type: Types::STRING, length: 150)]
    private string $email = '';

    #[ORM\Column(name: 'address', type: Types::STRING, length: 255)]
    private string $address = '';

    #[ORM\Column(name: 'password', type: Types::STRING, length: 255)]
    private string $password = '';

    #[ORM\Column(name: 'phone', type: Types::STRING, length: 20)]
    private string $phone = '';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, columnDefinition: 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'profile_pic', type: Types::STRING, length: 255, nullable: true)]
    private ?string $profilePic = null;

    #[ORM\Column(name: 'is_admin', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isAdmin = false;

    /**
     * @var Collection<int, LoginToken>
     */
    #[ORM\OneToMany(targetEntity: LoginToken::class, mappedBy: 'user')]
    private Collection $loginTokens;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user')]
    private Collection $notifications;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->loginTokens = new ArrayCollection();
        $this->notifications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getMiddleName(): ?string
    {
        return $this->middleName;
    }

    public function setMiddleName(?string $middleName): static
    {
        $this->middleName = $middleName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getProfilePic(): ?string
    {
        return $this->profilePic;
    }

    public function setProfilePic(?string $profilePic): static
    {
        $this->profilePic = $profilePic;

        return $this;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function setIsAdmin(bool $isAdmin): static
    {
        $this->isAdmin = $isAdmin;

        return $this;
    }

    /**
     * @return Collection<int, LoginToken>
     */
    public function getLoginTokens(): Collection
    {
        return $this->loginTokens;
    }

    public function addLoginToken(LoginToken $loginToken): static
    {
        if (!$this->loginTokens->contains($loginToken)) {
            $this->loginTokens->add($loginToken);
            $loginToken->setUser($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->isAdmin ? ['ROLE_ADMIN', 'ROLE_USER'] : ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}
