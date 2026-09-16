<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:set-role', description: 'Set a user role for testing')]
class SetUserRoleCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('role', InputArgument::REQUIRED, 'CANDIDATE, RECRUITER or ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $role = strtoupper($input->getArgument('role'));

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error("User not found: {$email}");
            return Command::FAILURE;
        }

        $roleMap = [
            'CANDIDATE' => 'ROLE_CANDIDATE',
            'RECRUITER' => 'ROLE_RECRUITER',
            'ADMIN' => 'ROLE_ADMIN',
        ];

        if (!isset($roleMap[$role])) {
            $io->error("Unknown role: {$role}. Use CANDIDATE, RECRUITER or ADMIN.");
            return Command::FAILURE;
        }

        $user->setRoles([$roleMap[$role]]);
        $this->em->flush();

        $io->success("User {$email} now has role {$roleMap[$role]}");
        return Command::SUCCESS;
    }
}
