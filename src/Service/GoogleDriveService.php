<?php

namespace App\Service;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;

class GoogleDriveService
{
    private Google_Service_Drive $driveService;

    public function __construct(string $serviceAccountJsonPath)
    {
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
                'q' => "'$folderId' in parents",
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

    public function getFolderId(string $folderName): ?string
    {
        $response = $this->driveService->files->listFiles([
            'q' => "mimeType='application/vnd.google-apps.folder' and name='$folderName'",
            'fields' => 'files(id)',
        ]);

        if (count($response->getFiles()) > 0) {
            return $response->getFiles()[0]->getId();
        }

        return null;
    }
}
