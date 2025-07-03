<?php

namespace App\Service;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Google_Service_Drive_Permission;
use Psr\Log\LoggerInterface;

class GoogleDriveService
{
    private Google_Service_Drive $driveService;
    private LoggerInterface $logger;
    private string $sharedEmail;

    public function __construct(string $serviceAccountJsonPath, LoggerInterface $logger, string $sharedEmail)
    {
        $this->logger = $logger;
        $this->sharedEmail = $sharedEmail;
        $client = new Google_Client();
        $client->setAuthConfig($serviceAccountJsonPath);
        $client->addScope(Google_Service_Drive::DRIVE);
        $this->driveService = new Google_Service_Drive($client);
    }

    public function uploadFile(string $filePath, string $folderId): string
    {
        $file = new Google_Service_Drive_DriveFile();
        $file->setName(basename($filePath));
        $file->setParents([$folderId]);

        $fileId = $this->driveService->files->create($file, [
            'data' => file_get_contents($filePath),
            'mimeType' => 'application/octet-stream',
            'uploadType' => 'multipart',
        ]);

        return $fileId->id;
    }

    public function listFiles(string $folderId): array
    {
        $files = [];
        $pageToken = null;

        do {
            $response = $this->driveService->files->listFiles([
                'q' => "'$folderId' in parents and trashed = false",
                'fields' => 'nextPageToken, files(id, name, createdTime)',
                'pageToken' => $pageToken,
            ]);

            $files = array_merge($files, $response->getFiles());
            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $files;
    }

    public function deleteFile(string $fileId): void
    {
        $this->driveService->files->delete($fileId);
    }

    public function findOrCreatePath(string $path): string
    {
        $parentId = 'root';
        $folders = explode('/', trim($path, '/'));

        foreach ($folders as $folderName) {
            $this->logger->info("Processing folder '{$folderName}' in parent '{$parentId}'.");
            $folderId = $this->findFolderIdByName($folderName, $parentId);
            if ($folderId) {
                $this->logger->info("Found folder '{$folderName}' with ID '{$folderId}'.");
                $parentId = $folderId;
            } else {
                $this->logger->info("Folder '{$folderName}' not found. Creating it.");
                $parentId = $this->createFolder($folderName, $parentId);
                $this->logger->info("Created folder '{$folderName}' with ID '{$parentId}'.");
            }
            // Share every folder in the path to ensure visibility.
            if ($parentId !== 'root') {
                $this->shareFile($parentId);
            }
        }

        return $parentId;
    }

    private function findFolderIdByName(string $folderName, string $parentId): ?string
    {
        $q = "mimeType='application/vnd.google-apps.folder' and name='$folderName' and '$parentId' in parents and trashed = false";
        $response = $this->driveService->files->listFiles([
            'q' => $q,
            'fields' => 'files(id)',
            'pageSize' => 1
        ]);

        if (count($response->getFiles()) > 0) {
            return $response->getFiles()[0]->getId();
        }

        return null;
    }

    private function createFolder(string $folderName, string $parentId): string
    {
        $fileMetadata = new Google_Service_Drive_DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId]
        ]);

        $folder = $this->driveService->files->create($fileMetadata, ['fields' => 'id']);

        return $folder->id;
    }

    private function isAlreadyShared(string $fileId): bool
    {
        try {
            $permissions = $this->driveService->permissions->listPermissions($fileId, ['fields' => 'permissions(emailAddress)']);
            foreach ($permissions->getPermissions() as $permission) {
                if ($permission->getEmailAddress() === $this->sharedEmail) {
                    $this->logger->info("File/folder with ID '{$fileId}' is already shared with '{$this->sharedEmail}'.");
                    return true;
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning("Could not check permissions for file/folder ID '{$fileId}': " . $e->getMessage());
            // Assume not shared if we can't check, to be safe.
        }
        return false;
    }

    private function shareFile(string $fileId): void
    {
        if ($this->isAlreadyShared($fileId)) {
            return;
        }

        $permission = new Google_Service_Drive_Permission([
            'type' => 'user',
            'role' => 'writer',
            'emailAddress' => $this->sharedEmail,
        ]);

        try {
            $this->driveService->permissions->create($fileId, $permission);
            $this->logger->info("Shared file/folder with ID '{$fileId}' with '{$this->sharedEmail}'.");
        } catch (\Exception $e) {
            $this->logger->error("Failed to share file/folder with ID '{$fileId}': " . $e->getMessage());
        }
    }
}
