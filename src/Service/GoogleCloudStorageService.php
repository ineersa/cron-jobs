<?php

namespace App\Service;

use Google\Cloud\Storage\StorageClient;
use Psr\Log\LoggerInterface;

class GoogleCloudStorageService
{
    private StorageClient $storageClient;
    private LoggerInterface $logger;
    private string $bucketName;

    public function __construct(string $serviceAccountJsonPath, string $bucketName, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->bucketName = $bucketName;
        $this->storageClient = new StorageClient([
            'keyFilePath' => $serviceAccountJsonPath,
        ]);
    }

    public function getBucketName(): string
    {
        return $this->bucketName;
    }

    public function uploadFile(string $filePath, string $objectName): void
    {
        $bucket = $this->storageClient->bucket($this->bucketName);
        $file = fopen($filePath, 'r');

        $this->logger->info("Uploading {$filePath} to gs://{$this->bucketName}/{$objectName}");

        $bucket->upload($file, [
            'name' => $objectName
        ]);

        $this->logger->info("Successfully uploaded {$objectName}.");
    }

    public function listFiles(string $prefix = ''): array
    {
        $bucket = $this->storageClient->bucket($this->bucketName);
        $options = [];
        if ($prefix) {
            $options['prefix'] = $prefix;
        }

        $objects = $bucket->objects($options);

        $files = [];
        foreach ($objects as $object) {
            $files[] = $object;
        }

        usort($files, function ($a, $b) {
            return $a->info()['timeCreated'] <=> $b->info()['timeCreated'];
        });

        return $files;
    }

    public function deleteFile(string $objectName): void
    {
        $bucket = $this->storageClient->bucket($this->bucketName);
        $object = $bucket->object($objectName);
        $object->delete();
        $this->logger->info("Deleted gs://{$this->bucketName}/{$objectName}");
    }
}
