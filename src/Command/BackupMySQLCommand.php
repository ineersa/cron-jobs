<?php

namespace App\Command;

use App\Service\DatabaseBackupService;
use App\Service\EmailService;
use App\Service\GoogleDriveService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:backup-mysql',
    description: 'Creates a backup of the MySQL database and uploads it to Google Drive.',
)]
class BackupMySQLCommand extends Command
{
    public function __construct(
        private readonly GoogleDriveService $googleDriveService,
        private readonly EmailService $emailService,
        private readonly DatabaseBackupService $databaseBackupService,
        private readonly LoggerInterface $logger,
        private readonly string $driveMysqlPath,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Starting MySQL backup process...');

        // Create archive
        $backupFileName = 'mysql_' . date('Y_m_d_H') . '.sql.gz';
        $backupFilePath = './data/' . $backupFileName;
        $this->databaseBackupService->backup($backupFilePath);

        $this->logger->info("Created backup archive: {$backupFileName}");

        // Upload to Google Drive
        $this->logger->info("Ensuring Google Drive folder '{$this->driveMysqlPath}' exists...");
        $folderId = $this->googleDriveService->findOrCreatePath($this->driveMysqlPath);
        $this->logger->info("Google Drive folder ID: {$folderId}");

        $this->logger->info("Uploading backup to Google Drive...");
        $fileUploaded = $this->googleDriveService->uploadFile($backupFilePath, $folderId);
        $this->logger->info("Uploaded backup to Google Drive.");

        // Clean up local archive
        unlink($backupFilePath);

        // Verify file exists and clean up old backups
        $this->logger->info("Verifying files in Google Drive folder...");
        $files = $this->googleDriveService->listFiles($folderId);
        $this->logger->info("Found " . count($files) . " files in the backup folder.");

        foreach ($files as $file) {
            $this->logger->info("  - {$file->getName()} (ID: {$file->getId()})");
        }

        if (count($files) > 3) {
            usort($files, fn($a, $b) => strtotime($a->getCreatedTime()) - strtotime($b->getCreatedTime()));
            $filesToDelete = array_slice($files, 0, count($files) - 5);

            foreach ($filesToDelete as $file) {
                $this->googleDriveService->deleteFile($file->getId());
                $this->logger->info("Deleted old backup: {$file->getName()}");
            }
        }

        $this->emailService->send(
            'MySQL backup successful',
            "Backup of blog database was successful. File link: {$fileUploaded->getWebViewLink()}"
        );

        $this->logger->info('Backup process completed successfully.');

        return Command::SUCCESS;
    }
}
