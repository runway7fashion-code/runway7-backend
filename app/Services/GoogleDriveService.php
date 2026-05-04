<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;

class GoogleDriveService
{
    private ?Drive $service = null;

    private function getService(): Drive
    {
        if ($this->service) return $this->service;

        $credentialsPath = config('google.drive.credentials');
        if (!file_exists($credentialsPath)) {
            throw new \RuntimeException('Google Drive credentials not found at: ' . $credentialsPath);
        }

        $client = new Client();
        $client->setAuthConfig($credentialsPath);
        $client->addScope(Drive::DRIVE);

        $this->service = new Drive($client);
        return $this->service;
    }

    /**
     * Create a folder in Google Drive.
     */
    public function createFolder(string $name, ?string $parentId = null): array
    {
        $parentId = $parentId ?? config('google.drive.parent_folder_id');

        $fileMetadata = new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId],
        ]);

        $folder = $this->getService()->files->create($fileMetadata, [
            'fields' => 'id, name, webViewLink',
            'supportsAllDrives' => true,
        ]);

        return [
            'id' => $folder->id,
            'name' => $folder->name,
            'url' => $folder->webViewLink,
        ];
    }

    /**
     * Create all 10 material subfolders for a designer.
     * Returns array of ['material_name' => ['id' => ..., 'url' => ...]].
     */
    public function createDesignerFolders(string $designerFolderName, ?string $parentId = null): array
    {
        $rootFolder = $this->createFolder($designerFolderName, $parentId);

        $materialFolders = [
            'Background',
            'Music',
            'Images',
            'Runway Logo',
            'Bio',
            'Hair Mood Board',
            'Makeup Mood Board',
            'Brand Logo',
            'Designer Photo',
            'Artworks',
        ];

        $result = [
            'root' => $rootFolder,
            'materials' => [],
        ];

        foreach ($materialFolders as $folderName) {
            $subfolder = $this->createFolder($folderName, $rootFolder['id']);
            $result['materials'][$folderName] = $subfolder;
        }

        return $result;
    }

    /**
     * Generate a resumable upload URL for direct browser-to-Drive upload.
     * The frontend uses this URL to upload files directly without passing through our server.
     *
     * When called from a browser, pass $origin (e.g. request->header('Origin')) so Google
     * adds CORS headers for that origin to subsequent PUT bytes requests. Native mobile clients
     * can leave $origin null since they bypass CORS.
     */
    public function generateResumableUploadUrl(string $folderId, string $fileName, string $mimeType, ?string $origin = null): string
    {
        $credentialsPath = config('google.drive.credentials');
        $client = new Client();
        $client->setAuthConfig($credentialsPath);
        $client->addScope(Drive::DRIVE);
        $client->setDefer(true);

        $service = new Drive($client);

        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
        ]);

        $request = $service->files->create($fileMetadata, [
            'uploadType' => 'resumable',
            'fields' => 'id, name, webViewLink, webContentLink, size',
            'supportsAllDrives' => true,
        ]);

        $headers = [
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-Upload-Content-Type' => $mimeType,
        ];
        if ($origin) {
            $headers['Origin'] = $origin;
        }

        // Execute deferred request to get the resumable upload URI
        $httpClient = $client->authorize();
        $response = $httpClient->request('POST', 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true', [
            'headers' => $headers,
            'json' => [
                'name' => $fileName,
                'parents' => [$folderId],
            ],
        ]);

        $uploadUrl = $response->getHeaderLine('Location');

        if (!$uploadUrl) {
            throw new \RuntimeException('Failed to get resumable upload URL from Google Drive');
        }

        return $uploadUrl;
    }

    /**
     * Download a file's binary content from Drive. Supports HTTP Range requests for
     * progressive media playback: pass the Range header verbatim (e.g. "bytes=0-1023")
     * and the response will reflect Drive's 206/200 response with Content-Range / Content-Length.
     *
     * Returns:
     *   body            - StreamInterface (read in chunks; do not load fully into memory)
     *   mime_type       - mime from Drive metadata
     *   size            - total file size in bytes
     *   name            - file name on Drive
     *   status_code     - 200 (full) or 206 (partial)
     *   content_range   - Content-Range header from Drive (only when 206)
     *   content_length  - Content-Length header from the response (partial or full)
     */
    public function downloadFile(string $fileId, ?string $range = null): array
    {
        $service = $this->getService();

        $meta = $service->files->get($fileId, [
            'fields' => 'mimeType, size, name',
            'supportsAllDrives' => true,
        ]);

        // Use the authorized HTTP client directly so we can pass Range and stream the body.
        $credentialsPath = config('google.drive.credentials');
        $client = new Client();
        $client->setAuthConfig($credentialsPath);
        $client->addScope(Drive::DRIVE);
        $httpClient = $client->authorize();

        $headers = [];
        if ($range) {
            $headers['Range'] = $range;
        }

        $url = "https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media&supportsAllDrives=true";
        $response = $httpClient->request('GET', $url, [
            'headers'     => $headers,
            'stream'      => true,
            'http_errors' => false,
        ]);

        return [
            'body'           => $response->getBody(),
            'mime_type'      => $meta->mimeType,
            'size'           => (int) $meta->size,
            'name'           => $meta->name,
            'status_code'    => $response->getStatusCode(),
            'content_range'  => $response->getHeaderLine('Content-Range') ?: null,
            'content_length' => $response->getHeaderLine('Content-Length') !== ''
                ? (int) $response->getHeaderLine('Content-Length')
                : null,
        ];
    }

    /**
     * Get file metadata from Drive.
     */
    public function getFile(string $fileId): array
    {
        $file = $this->getService()->files->get($fileId, [
            'fields' => 'id, name, mimeType, size, webViewLink, webContentLink, thumbnailLink',
            'supportsAllDrives' => true,
        ]);

        return [
            'id' => $file->id,
            'name' => $file->name,
            'mime_type' => $file->mimeType,
            'size' => $file->size,
            'view_url' => $file->webViewLink,
            'download_url' => $file->webContentLink,
            'thumbnail_url' => $file->thumbnailLink,
        ];
    }

    /**
     * List files in a folder.
     */
    public function listFiles(string $folderId, int $limit = 100): array
    {
        $results = $this->getService()->files->listFiles([
            'q' => "'{$folderId}' in parents and trashed = false",
            'fields' => 'files(id, name, mimeType, size, webViewLink, webContentLink, thumbnailLink, createdTime)',
            'pageSize' => $limit,
            'orderBy' => 'createdTime desc',
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ]);

        return array_map(fn($file) => [
            'id' => $file->id,
            'name' => $file->name,
            'mime_type' => $file->mimeType,
            'size' => $file->size,
            'view_url' => $file->webViewLink,
            'download_url' => $file->webContentLink,
            'thumbnail_url' => $file->thumbnailLink,
            'created_at' => $file->createdTime,
        ], $results->getFiles());
    }

    /**
     * Delete a file from Drive (moves to trash — recoverable for 30 days in Shared Drives).
     */
    public function deleteFile(string $fileId): void
    {
        $this->getService()->files->update($fileId, new DriveFile(['trashed' => true]), [
            'supportsAllDrives' => true,
        ]);
    }

    /**
     * Share a folder with a specific email (view only).
     */
    public function shareFolder(string $folderId, string $email, string $role = 'reader'): void
    {
        $permission = new \Google\Service\Drive\Permission([
            'type' => 'user',
            'role' => $role,
            'emailAddress' => $email,
        ]);

        $this->getService()->permissions->create($folderId, $permission, [
            'sendNotificationEmail' => false,
            'supportsAllDrives' => true,
        ]);
    }
}
