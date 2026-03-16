<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Videos\ProcessVideoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\VideoUploadRequest;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Throwable;

final class VideoUploaderController extends Controller
{
    public function __invoke(VideoUploadRequest $request, ProcessVideoAction $action): JsonResponse
    {
        Gate::authorize('create', [Video::class]);

        try {
            $video = $action->handle(
                $request->user(),
                $request->file('video'),
            );

            return response()
                ->json([
                    'id' => $video->id,
                ])
                ->setStatusCode(ResponseAlias::HTTP_ACCEPTED)
                ->header('Retry-After', 300);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed uploading the video, please try again later.'
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
