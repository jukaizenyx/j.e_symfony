<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create the first administrator account using the configured database.',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->users->findOneBy(['isAdmin' => true]) instanceof User) {
            $io->error('An administrator already exists. This command only creates the first administrator.');

            return Command::FAILURE;
        }

        $username = $io->ask('Username', null, static function (?string $value): string {
            $value = trim((string) $value);
            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $value)) {
                throw new \RuntimeException('Use 3 to 20 letters, numbers, or underscores.');
            }

            return $value;
        });
        $firstName = $io->ask('First name', null, self::nameValidator(...));
        $lastName = $io->ask('Last name', null, self::nameValidator(...));
        $email = $io->ask('Email address', null, static function (?string $value): string {
            $value = trim((string) $value);
            if (!filter_var($value, FILTER_VALIDATE_EMAIL) || strlen($value) > 150) {
                throw new \RuntimeException('Enter a valid email address up to 150 characters.');
            }

            return $value;
        });
        $address = $io->ask('Address', null, static function (?string $value): string {
            $value = trim((string) $value);
            if ($value === '' || strlen($value) > 255) {
                throw new \RuntimeException('Enter an address up to 255 characters.');
            }

            return $value;
        });
        $phone = $io->ask('Phone number', null, static function (?string $value): string {
            $value = trim((string) $value);
            if (!preg_match('/^[0-9]{10,15}$/', $value)) {
                throw new \RuntimeException('Enter 10 to 15 digits.');
            }

            return $value;
        });

        if ($this->users->findOneBy(['username' => $username]) || $this->users->findOneBy(['email' => $email])) {
            $io->error('That username or email address is already in use.');

            return Command::FAILURE;
        }

        $password = $io->askHidden('Password (minimum 8 characters with uppercase, lowercase, number, and symbol)', self::passwordValidator(...));
        $confirmation = $io->askHidden('Confirm password');
        if ($password !== $confirmation) {
            $io->error('The passwords do not match.');

            return Command::FAILURE;
        }

        if (!$io->confirm(sprintf('Create administrator account "%s" in the configured JE Essentials database?', $username), false)) {
            $io->warning('No account was created.');

            return Command::SUCCESS;
        }

        $admin = (new User())
            ->setUsername($username)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setEmail($email)
            ->setAddress($address)
            ->setPhone($phone)
            ->setIsAdmin(true);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $password));

        $this->entityManager->persist($admin);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $io->error('That username or email address is already in use.');

            return Command::FAILURE;
        }

        $io->success('Administrator account created. Sign in through the website to manage the catalog and orders.');

        return Command::SUCCESS;
    }

    private static function nameValidator(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > 50 || !preg_match('/^[a-zA-Z ]+$/', $value)) {
            throw new \RuntimeException('Use 1 to 50 letters and spaces.');
        }

        return $value;
    }

    private static function passwordValidator(?string $value): string
    {
        $value = (string) $value;
        if (
            strlen($value) < 8
            || !preg_match('/[A-Z]/', $value)
            || !preg_match('/[a-z]/', $value)
            || !preg_match('/[0-9]/', $value)
            || !preg_match('/[\W_]/', $value)
        ) {
            throw new \RuntimeException('Use at least 8 characters with uppercase, lowercase, number, and symbol.');
        }

        return $value;
    }
}
