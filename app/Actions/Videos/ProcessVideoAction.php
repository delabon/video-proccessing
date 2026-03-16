<?php

declare(strict_types=1);

namespace App\Actions\Videos;

use App\Enums\VideoStatus;
use App\Jobs\ProcessVideo;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class ProcessVideoAction
{
    /**
     * @throws Throwable
     */
    public function handle(User $user, UploadedFile $file): Video
    {
        $disk = config('filesystems.default');
        $path = $file->store($user->id . '/videos');

        $this->validatePath($path, $user, $disk, $file);

        try {
            DB::beginTransaction();

            // Why using a transaction here? well because we're creating a video and a job in DB for processing the video in the queue
            $video = $user->videos()
                ->create([
                    'name' => $file->getClientOriginalName(),
                    'type' => $file->getClientMimeType(),
                    'disk' => $disk,
                    'path' => $path,
                    'status' => VideoStatus::Uploaded,
                ]);

            ProcessVideo::dispatch($video->id);

            Cache::forget('user_' . $user->id . '_videos_count');

            DB::commit();

            return $video;
        } catch (Throwable $e) {
            DB::rollBack();

            // Remove the video
            try {
                Storage::disk($disk)->delete($path);
            } catch (Throwable) {}

            Log::error('Upload failed', [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    private function validatePath(false|string $path, User $user, mixed $disk, UploadedFile $file): void
    {
        if (!$path) {
            // Error storing the video.
            Log::error('Video upload failed', [
                'user_id' => $user->id,
                'disk' => $disk,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);

            throw new RuntimeException('Upload path for videos does not exist.');
        }
    }
}
