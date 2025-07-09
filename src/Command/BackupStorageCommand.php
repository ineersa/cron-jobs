<?php

namespace App\Command;

use App\Service\EmailService;
use App\Service\GoogleDriveService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backup-storage',
    description: 'Creates a backup of the storage folder and uploads it to Google Drive.',
)]
class BackupStorageCommand extends Command
{
    public function __construct(
        private readonly GoogleDriveService $googleDriveService,
        private readonly EmailService $emailService,
        private readonly string $localStorageFolder,
        private readonly string $driveStoragePath,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting storage backup process...');

        // Create archive
        $backupFileName = 'storage_' . date('Y_m_d_H') . '.tar.gz';
        $backupFilePath = './data/' . $backupFileName;
        $command = "tar -czvf {$backupFilePath} -C {$this->localStorageFolder} .";
        shell_exec($command);

        $io->info("Created backup archive: {$backupFileName}");

        // Upload to Google Drive
        $io->info("Ensuring Google Drive folder '{$this->driveStoragePath}' exists...");
        $folderId = $this->googleDriveService->findOrCreatePath($this->driveStoragePath);
        $io->info("Google Drive folder ID: {$folderId}");

        $io->info("Uploading backup to Google Drive...");
        $fileUploaded = $this->googleDriveService->uploadFile($backupFilePath, $folderId);
        $io->info("Uploaded backup to Google Drive.");

        // Clean up local archive
        unlink($backupFilePath);

        // Verify file exists and clean up old backups
        $io->info("Verifying files in Google Drive folder...");
        $files = $this->googleDriveService->listFiles($folderId);
        $io->info("Found " . count($files) . " files in the backup folder.");

        foreach ($files as $file) {
            $io->info("  - {$file->getName()} (ID: {$file->getId()})");
        }

        if (count($files) > 5) {
            usort($files, fn($a, $b) => strtotime($a->getCreatedTime()) - strtotime($b->getCreatedTime()));
            $filesToDelete = array_slice($files, 0, count($files) - 5);

            foreach ($filesToDelete as $file) {
                $this->googleDriveService->deleteFile($file->getId());
                $io->info("Deleted old backup: {$file->getName()}");
            }
        }

        $this->emailService->send(
            'Storage backup successful',
            "Backup of {$this->localStorageFolder} was successful. File link: {$fileUploaded->getWebViewLink()}"
        );

        $io->success('Backup process completed successfully.');

        return Command::SUCCESS;
    }
}
