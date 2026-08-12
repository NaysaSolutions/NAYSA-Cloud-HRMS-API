<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileAttachmentController extends Controller
{
    // =========================================================
    // LOAD ATTACHMENTS
    // =========================================================

    public function get(Request $request)
    {
        $params = $request->input('documentID');

        try {
            $results = DB::select(
                'EXEC sproc_PHP_AttTran @mode = ?, @tranid = ?',
                ['Load', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Load attachments failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================
    // CONFIG HELPERS
    // =========================================================

    private function attachmentStorageMode(): string
    {
        return strtolower(trim(env('ATTACHMENT_STORAGE', 'local')));
    }

    private function isGoogleDriveMode(): bool
    {
        return $this->attachmentStorageMode() === 'gdrive';
    }

    private function getGoogleDriveFolderId(): string
    {
        $value = trim(env('GOOGLE_DRIVE_MAIN_FOLDER_ID', ''));

        if ($value === '') {
            return '';
        }

        // Example:
        // https://drive.google.com/drive/u/2/folders/1j2FZLvwhTVaQbXOmOAEV9QUSsMFTYgGp
        if (str_contains(strtolower($value), '/folders/')) {
            $parts = explode('/folders/', $value);
            $value = $parts[1] ?? '';

            $value = preg_split('/[?\/&]/', $value)[0] ?? '';
        }

        return trim($value);
    }

    private function getGoogleDriveFileId(string $fileIdOrLink): string
    {
        $value = trim($fileIdOrLink);

        if ($value === '') {
            return '';
        }

        // Example:
        // https://drive.google.com/file/d/FILE_ID/view
        if (str_contains(strtolower($value), '/file/d/')) {
            $parts = explode('/file/d/', $value);
            $value = $parts[1] ?? '';

            $value = preg_split('/[?\/&]/', $value)[0] ?? '';
        }

        // Example:
        // https://drive.google.com/open?id=FILE_ID
        if (str_contains(strtolower($value), 'id=')) {
            $parts = explode('id=', $value);
            $value = $parts[1] ?? '';

            $value = preg_split('/[?\/&]/', $value)[0] ?? '';
        }

        return trim($value);
    }

    // =========================================================
    // GOOGLE DRIVE HELPERS
    // =========================================================

 private function getGoogleDriveAccessToken(): string
{
    $clientId = env('GOOGLE_DRIVE_CLIENT_ID');
    $clientSecret = env('GOOGLE_DRIVE_CLIENT_SECRET');
    $refreshToken = env('GOOGLE_DRIVE_REFRESH_TOKEN');

    if (!$clientId || !$clientSecret || !$refreshToken) {
        throw new \Exception('Google Drive credentials are incomplete in .env.');
    }

    $response = Http::withoutVerifying()
        ->asForm()
        ->timeout(60)
        ->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

    if (!$response->successful()) {
        throw new \Exception('Google Drive token failed: ' . $response->body());
    }

    return $response->json('access_token');
}

    private function uploadToGoogleDrive($file, string $generatedId): string
    {
        $accessToken = $this->getGoogleDriveAccessToken();

        $folderId = $this->getGoogleDriveFolderId();

        if ($folderId === '') {
            throw new \Exception('Google Drive main folder ID is not defined.');
        }

        // Upload name in Google Drive.
        // No original filename.
        // No file extension.
        $metadata = [
            'name' => $generatedId,
            'parents' => [$folderId],
        ];

        $boundary = 'naysa_' . Str::random(32);

        $fileContent = file_get_contents($file->getRealPath());

        $body =
            "--{$boundary}\r\n" .
            "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
            json_encode($metadata) . "\r\n" .
            "--{$boundary}\r\n" .
            "Content-Type: application/octet-stream\r\n\r\n" .
            $fileContent . "\r\n" .
            "--{$boundary}--";

        $response = Http::withToken($accessToken)
            ->timeout(300)
            ->withHeaders([
                'Content-Type' => 'multipart/related; boundary=' . $boundary,
            ])
            ->send(
                'POST',
                'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,webViewLink',
                [
                    'body' => $body,
                ]
            );

        if (!$response->successful()) {
            throw new \Exception('Google Drive upload failed: ' . $response->body());
        }

        return $response->json('id');
    }

    private function downloadFromGoogleDrive(string $fileId): string
    {
        $accessToken = $this->getGoogleDriveAccessToken();

        $googleFileId = $this->getGoogleDriveFileId($fileId);

        if ($googleFileId === '') {
            throw new \Exception('Google Drive file ID is not defined.');
        }

        $response = Http::withToken($accessToken)
            ->timeout(300)
            ->get(
                'https://www.googleapis.com/drive/v3/files/' .
                urlencode($googleFileId) .
                '?alt=media'
            );

        if (!$response->successful()) {
            throw new \Exception('Google Drive download failed: ' . $response->body());
        }

        return $response->body();
    }

    private function deleteFromGoogleDrive(string $fileId): void
    {
        $accessToken = $this->getGoogleDriveAccessToken();

        $googleFileId = $this->getGoogleDriveFileId($fileId);

        if ($googleFileId === '') {
            throw new \Exception('Google Drive file ID is not defined.');
        }

        $response = Http::withToken($accessToken)
            ->timeout(120)
            ->delete(
                'https://www.googleapis.com/drive/v3/files/' .
                urlencode($googleFileId)
            );

        // 404 means already missing in Drive.
        // We can still continue deleting the DB record.
        if (!$response->successful() && $response->status() !== 404) {
            throw new \Exception('Google Drive delete failed: ' . $response->body());
        }
    }

    // =========================================================
    // UPLOAD ATTACHMENT
    // =========================================================

    public function attachFile(Request $request)
    {
        try {
            $files = $request->file('files');
            $modifiedDates = $request->input('modifiedDate', []);
            $uploadedDates = $request->input('uploadedDate', []);
            $tranID = $request->input('documentID');

            if (!$files || count($files) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No files selected.',
                ], 400);
            }

            if (!$tranID) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document ID is required.',
                ], 400);
            }

            $responses = [];

            foreach ($files as $index => $file) {
                // Generated local/system filename.
                // For local mode: this is the stored file name.
                // For GDrive mode: this is the uploaded Drive display name.
                $generatedId = (string) Str::uuid();

                // Original filename for user display and download restore.
                $originalName = $file->getClientOriginalName();

                $fileIdToSave = $generatedId;
                $path = null;

                if ($this->isGoogleDriveMode()) {
                    // Save Google Drive returned file ID into database FILE_ID.
                    $fileIdToSave = $this->uploadToGoogleDrive($file, $generatedId);
                    $path = 'Google Drive';
                } else {
                    // Store local file without extension using generated ID.
                    $path = $file->storeAs('', $generatedId, 'public_uploads');
                }

                $modified = $modifiedDates[$index] ?? now();
                $uploaded = $uploadedDates[$index] ?? now();

                DB::statement("EXEC sproc_PHP_AttTran ?, ?, ?, ?, ?, ?", [
                    'upload',
                    $fileIdToSave,
                    $originalName,
                    $modified,
                    $uploaded,
                    $tranID,
                ]);

                $responses[] = [
                    'id' => $fileIdToSave,
                    'file_name' => $originalName,
                    'path' => $path,
                    'storage' => $this->attachmentStorageMode(),
                    'date_modified' => $modified,
                    'date_uploaded' => $uploaded,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Files uploaded successfully',
                'data' => $responses,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Upload failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================
    // DELETE ATTACHMENT
    // =========================================================

    public function deleteFile(Request $request, $id)
    {
        try {
            $fileRecord = DB::table('ATT_TRAN')
                ->where('FILE_ID', $id)
                ->first();

            if (!$fileRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            if ($this->isGoogleDriveMode()) {
                $this->deleteFromGoogleDrive($fileRecord->FILE_ID);
            } else {
                if (Storage::disk('public_uploads')->exists($fileRecord->FILE_ID)) {
                    Storage::disk('public_uploads')->delete($fileRecord->FILE_ID);
                }
            }

            DB::statement("EXEC sproc_PHP_AttTran ?, ?", [
                'delete',
                $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully',
                'file' => $fileRecord,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Delete failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Delete failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================
    // DOWNLOAD SINGLE ATTACHMENT
    // =========================================================

    public function downloadFile(Request $request, $id)
    {
        try {
            $file = DB::table('ATT_TRAN')
                ->where('FILE_ID', $id)
                ->first();

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            if ($this->isGoogleDriveMode()) {
                $fileContent = $this->downloadFromGoogleDrive($file->FILE_ID);

                return response($fileContent, 200, [
                    'Content-Type' => 'application/octet-stream',
                    'Content-Disposition' => 'attachment; filename="' . addslashes($file->FILE_NAME) . '"',
                ]);
            }

            if (!Storage::disk('public_uploads')->exists($file->FILE_ID)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found on server',
                ], 404);
            }

            $storagePath = Storage::disk('public_uploads')->path($file->FILE_ID);

            return response()->download($storagePath, $file->FILE_NAME);

        } catch (\Exception $e) {
            Log::error('Download failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Download failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================
    // DOWNLOAD ALL ATTACHMENTS AS ZIP
    // =========================================================

    public function downloadAll(Request $request, $documentID)
    {
        try {
            $files = DB::table('ATT_TRAN')
                ->where('TRAN_ID', $documentID)
                ->get();

            if ($files->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No attachments found',
                ], 404);
            }

            $zipFileName = "attachments_{$documentID}.zip";
            $tempPath = storage_path('app/temp');

            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0777, true);
            }

            $zipPath = $tempPath . DIRECTORY_SEPARATOR . $zipFileName;

            $zip = new \ZipArchive;

            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not create ZIP file',
                ], 500);
            }

            $addedCount = 0;

            foreach ($files as $file) {
                $safeName = preg_replace('/[\/:*?"<>|]/', '_', $file->FILE_NAME);

                if ($safeName === '') {
                    $safeName = $file->FILE_ID;
                }

                if ($this->isGoogleDriveMode()) {
                    try {
                        $fileContent = $this->downloadFromGoogleDrive($file->FILE_ID);

                        $zip->addFromString($safeName, $fileContent);
                        $addedCount++;

                    } catch (\Exception $e) {
                        Log::warning(
                            'Google Drive file skipped: ' .
                            $file->FILE_ID .
                            ' - ' .
                            $e->getMessage()
                        );
                    }
                } else {
                    if (Storage::disk('public_uploads')->exists($file->FILE_ID)) {
                        $filePath = Storage::disk('public_uploads')->path($file->FILE_ID);

                        $zip->addFile($filePath, $safeName);
                        $addedCount++;
                    } else {
                        Log::warning('Local file not found: ' . $file->FILE_ID);
                    }
                }
            }

            $zip->close();

            if ($addedCount === 0) {
                if (file_exists($zipPath)) {
                    unlink($zipPath);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'No files were available for download.',
                ], 404);
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            return response()
                ->download($zipPath, $zipFileName)
                ->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('DownloadAll failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Download failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}