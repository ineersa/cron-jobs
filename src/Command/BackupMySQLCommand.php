<?php

namespace App\Command;

use App\Service\DatabaseBackupService;
use App\Service\EmailService;
use App\Service\GoogleCloudStorageService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:backup-mysql',
    description: 'Creates a backup of the MySQL database and uploads it to Google Cloud Storage.',
)]
class BackupMySQLCommand extends Command
{
    public function __construct(
        private readonly GoogleCloudStorageService $googleCloudStorageService,
        private readonly EmailService $emailService,
        private readonly DatabaseBackupService $databaseBackupService,
        private readonly LoggerInterface $logger,
        private readonly string $gcsPrefixPath,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Starting MySQL backup process...');

        // Create archive
        $backupFileName = 'mysql_' . date('Y_m_d_H_i_s') . '.sql.gz';
        $backupFilePath = './data/' . $backupFileName;
        $this->databaseBackupService->backup($backupFilePath);

        $this->logger->info("Created backup archive: {$backupFileName}");

        // Upload to Google Cloud Storage
        $objectName = $this->gcsPrefixPath . $backupFileName;
        $this->googleCloudStorageService->uploadFile($backupFilePath, $objectName);

        // Clean up local archive
        unlink($backupFilePath);

        // Verify file exists and clean up old backups
        $this->logger->info("Verifying files in GCS bucket...");
        $files = $this->googleCloudStorageService->listFiles($this->gcsPrefixPath);
        $this->logger->info("Found " . count($files) . " files in the backup folder.");

        if (count($files) > 3) {
            $filesToDelete = array_slice($files, 0, count($files) - 3);

            foreach ($filesToDelete as $file) {
                $this->googleCloudStorageService->deleteFile($file->name());
            }
        }

        $this->emailService->send(
            'MySQL backup successful',
            "Backup of blog database was successful. File gs://{$this->googleCloudStorageService->getBucketName()}/{$objectName} was created."
        );

        $this->logger->info('Backup process completed successfully.');

        return Command::SUCCESS;
    }
}
