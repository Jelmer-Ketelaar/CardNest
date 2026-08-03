<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:e2e:verify-user', description: 'Verify a synthetic real-E2E account')]
final class VerifyE2eUserCommand extends Command
{
    public function __construct(private readonly UserRepository $users)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Synthetic @example.test account email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        if (!str_ends_with($email, '@example.test')) {
            $output->writeln('<error>Only synthetic @example.test accounts can be verified.</error>');

            return self::INVALID;
        }

        $user = $this->users->findByEmail($email);
        if (!$user instanceof User) {
            $output->writeln('<error>The synthetic E2E account does not exist.</error>');

            return self::FAILURE;
        }

        $user->verifyEmail();
        $this->users->save($user);
        $output->writeln('<info>The synthetic E2E account is verified.</info>');

        return self::SUCCESS;
    }
}
