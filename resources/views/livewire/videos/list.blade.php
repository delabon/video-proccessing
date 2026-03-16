<?php

use App\Models\Video;
use App\Models\VideoVariant;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithPagination;

    public function with(): array
    {
        return [
            'videos' => auth()->user()
                ->videos()
                ->with(['variants'])
                ->latest()
                ->simplePaginate(10),
        ];
    }

    public function delete(int $id): void
    {
        $user = auth()->user();
        $video = $user->videos()
            ->findOrFail($id);

        $this->authorize('delete', $video);

        $deletingFiles = [];
        $deletingFiles[] = [
            'disk' => $video->disk,
            'path' => $video->path
        ];

        $video->variants->each(function (VideoVariant $videoVariant) use (&$deletingFiles) {
            $deletingFiles[] = [
                'disk' => $videoVariant->disk,
                'path' => $videoVariant->path
            ];
        });

        foreach ($deletingFiles as $deletingFile) {
            $result = Storage::disk($deletingFile['disk'])
                ->delete($deletingFile['path']);

            if (!$result) {
                Log::warning("Failed to delete file: {$deletingFile['path']}");
            }
        }

        $video->delete();

        Cache::forget('user_' . $user->id . '_videos_count');

        $this->dispatch('re-count-videos');

        $this->js('alert("Your video has been deleted.")');
    }
}; ?>

<div>
    @if($videos->isNotEmpty())
        <table class="table-auto w-full table-videos">
            <thead>
                <tr class="align-top">
                    <th class="text-left border-b">ID</th>
                    <th class="text-left border-b">Name</th>
                    <th class="text-left border-b">Status</th>
                    <th class="text-left border-b">Resolutions</th>
                    <th class="text-left border-b">Uploaded At</th>
                    <th class="text-left border-b">Updated At</th>
                    <th class="text-left border-b">Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($videos as $video)
                <tr class="align-top">
                    <td>{{ $video->id }}</td>
                    <td>{{ $video->name }}</td>
                    <td>{{ $video->status->label() }}</td>
                    <td>
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('videos.download', $video->id) }}" target="_blank" class="underline">Original</a>

                            @if(!empty($video->variants))
                                @foreach($video->variants as $variant)
                                    <a href="{{ route('videos.variants.download', $variant->id) }}" target="_blank"
                                       class="underline">{{ $variant->resolution->value }}</a>
                                @endforeach
                            @endif
                        </div>
                    </td>
                    <td>{{ $video->created_at->format('M j, Y') }}</td>
                    <td>{{ $video->updated_at->format('M j, Y') }}</td>
                    <td>
                        <flux:button
                            wire:click="delete({{ $video->id }})"
                            wire:confirm="Are you sure you want to delete this video?"
                            variant="danger"
                            class="cursor-pointer"
                        >Delete
                        </flux:button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="mt-6">
            {{ $videos->links() }}
        </div>
    @else
        <p>No videos yet. Please upload one using our API.</p>
    @endif
</div>
