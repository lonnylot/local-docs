<div>
    <form wire:submit="update" class="relative p-4">
        <div class="absolute top-0 left-0 right-0 bottom-0 z-10" wire:loading>
            <div class="flex h-screen items-center justify-center">
                <span class="animate-pulse">updating...</span>
            </div>
        </div>
        <div wire:loading.class='opacity-25'>
            <div class="sticky top-0 bg-white">
                <div class="p-4">
                    <div class="flex justify-between">
                        <div>
                            <h2 class="text-2xl font-bold">Local Docs</h2>
                            <p class="text-gray-500">Local Laravel docs on your desktop</p>
                        </div>
                        <div class="flex gap-2 items-start">
                            <select wire:change="changeVersion($event.target.value)" wire:model="selectedVersion"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                @foreach ($versions as $version)
                                    <option value="{{ $version }}">{{ $version }}</option>
                                @endforeach
                            </select>
                            <button type="submit" wire.loading.class="animate-spin">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="prose flex flex-row gap-4">
                <div class="basis-2/5">
                    {!! $nav !!}
                </div>
                <div class="basis-3/5">
                    <livewire:documentation :version="$selectedVersion" :doc="$doc" />
                </div>
            </div>
        </div>
    </form>
</div>
