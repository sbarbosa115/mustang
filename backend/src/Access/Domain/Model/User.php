<?php

namespace App\Access\Domain\Model;

use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * A person who signs in. Email is unique across Mustang so sign-in needs no dealership picker.
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\Index(name: 'idx_users_dealership', columns: ['dealership_id'])]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\Column(type: 'string', length: 180, name: 'email')]
    private string $email;
    #[ORM\Column(type: 'string', length: 255, name: 'password_hash')]
    private string $passwordHash;
    #[ORM\Column(type: 'string', length: 80, name: 'first_name')]
    private string $firstName;
    #[ORM\Column(type: 'string', length: 80, name: 'last_name')]
    private string $lastName;
    #[ORM\Column(type: 'string', length: 20, enumType: Role::class, name: 'role')]
    private Role $role;
    #[ORM\Column(type: 'boolean', name: 'active')]
    private bool $active;
    #[ORM\Column(type: 'string', length: 20, enumType: Theme::class, name: 'theme')]
    private Theme $theme;
    #[ORM\Column(type: 'datetime_immutable', name: 'last_login_at', nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt;

    public function __construct(
        string $dealershipId,
        string $email,
        string $passwordHash,
        string $firstName,
        string $lastName,
        Role $role,
        bool $active = true,
        Theme $theme = Theme::System,
        ?\DateTimeImmutable $lastLoginAt = null,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->role = $role;
        $this->active = $active;
        $this->theme = $theme;
        $this->lastLoginAt = $lastLoginAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function dealershipId(): string
    {
        return $this->dealershipId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function theme(): Theme
    {
        return $this->theme;
    }

    public function lastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function recordSignIn(\DateTimeImmutable $at): void
    {
        $this->lastLoginAt = $at;
    }

    public function changeTheme(Theme $theme): void
    {
        $this->theme = $theme;
    }
}
