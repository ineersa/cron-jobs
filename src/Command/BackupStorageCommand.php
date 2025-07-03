<?php

namespace App\Command;

use App\Service\GoogleDriveService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backup-storage',
    description: 'Creates a backup of the storage folder and uploads it to Google Drive.',
)]
class BackupStorageCommand extends Command
{
    private GoogleDriveService $googleDriveService;
    private string $localStorageFolder;
    private string $driveStoragePath;

    public function __construct(GoogleDriveService $googleDriveService, string $localStorageFolder, string $driveStoragePath)
    {
        $this->googleDriveService = $googleDriveService;
        $this->localStorageFolder = $localStorageFolder;
        $this->driveStoragePath = $driveStoragePath;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Starting storage backup process...');

        // Create archive
        $backupFileName = 'backup-' . date('Y-m-d-H-i-s') . '.tar.gz';
        $backupFilePath = '/tmp/' . $backupFileName;
        $command = "tar -czvf {$backupFilePath} -C {$this->localStorageFolder} .";
        shell_exec($command);

        $io->info("Created backup archive: {$backupFileName}");

        // Upload to Google Drive
        $folderId = $this->googleDriveService->getFolderId($this->driveStoragePath);
        if (!$folderId) {
            $io->error("Google Drive folder '{$this->driveStoragePath}' not found.");
            return Command::FAILURE;
        }

        $this->googleDriveService->uploadFile($backupFilePath, $folderId);
        $io->info("Uploaded backup to Google Drive.");

        // Clean up local archive
        unlink($backupFilePath);

        // Clean up old backups
        $files = $this->googleDriveService->listFiles($folderId);
        if (count($files) > 5) {
            usort($files, fn($a, $b) => strtotime($a->getCreatedTime()) - strtotime($b->getCreatedTime()));
            $filesToDelete = array_slice($files, 0, count($files) - 5);

            foreach ($filesToDelete as $file) {
                $this->googleDriveService->deleteFile($file->getId());
                $io->info("Deleted old backup: {$file->getName()}");
            }
        }

        $io->success('Backup process completed successfully.');

        return Command::SUCCESS;
    }
}