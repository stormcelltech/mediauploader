{{-- Media Gallery Component --}}
<div class="media-gallery">
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
        @forelse($media ?? [] as $item)
            <div class="group relative rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800 aspect-square">
                {{-- Image --}}
                @if ($item->isImage())
                    <img src="{{ $item->getUrl('300x300') }}" alt="{{ $item->name }}"
                        class="w-full h-full object-cover group-hover:opacity-75 transition-opacity" loading="lazy">
                @else
                    <div class="w-full h-full flex items-center justify-center bg-gray-200 dark:bg-gray-700">
                        <svg class="w-8 h-8 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z">
                            </path>
                        </svg>
                    </div>
                @endif

                {{-- Overlay --}}
                <div
                    class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100">
                    <a href="{{ $item->getUrl() }}" target="_blank" rel="noopener noreferrer"
                        class="p-2 bg-blue-500 text-white rounded-full hover:bg-blue-600 transition-colors"
                        title="View">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                            </path>
                        </svg>
                    </a>

                    @if ($deletable ?? true)
                        <button type="button"
                            class="p-2 bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors delete-media"
                            data-media-id="{{ $item->id }}" title="Delete">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                    clip-rule="evenodd"></path>
                            </svg>
                        </button>
                    @endif
                </div>

                {{-- Filename --}}
                <div
                    class="absolute bottom-0 left-0 right-0 p-2 bg-gradient-to-t from-black/80 to-transparent text-white text-xs font-medium truncate">
                    {{ $item->name }}
                </div>
            </div>
        @empty
            <div class="col-span-full flex flex-col items-center justify-center py-12 text-gray-500">
                <svg class="w-12 h-12 mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                    </path>
                </svg>
                <p>{{ $emptyMessage ?? 'No media files found' }}</p>
            </div>
        @endforelse
    </div>
</div>
@pushOnce('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.delete-media').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!confirm('Are you sure you want to delete this file?')) return;

                    const mediaId = this.dataset.mediaId;
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

                    fetch(`/media/${mediaId}/delete`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 200) {
                                this.closest('.group')?.remove();
                            }
                        });
                });
            });
        });
    </script>
@endPushOnce
