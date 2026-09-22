<x-layout title="My Profile">
        <x-page-header
            title="My Profile"
            subtitle="Your photo, shown wherever your account appears in the system."
            :back="route(auth()->user()->homeRouteName())"
            back-label="Back"
        />

        @if (session('status'))
            <div class="mb-6 rounded-lg bg-brand-50 border border-brand-600/30 text-brand-800 text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-panel border border-border rounded-xl shadow-sm p-6 max-w-lg">
            <div class="flex items-center gap-5 mb-6">
                @if ($user->profilePhotoUrl())
                    <img src="{{ $user->profilePhotoUrl() }}" alt="{{ $user->name }}" class="w-20 h-20 rounded-full object-cover ring-1 ring-border">
                @else
                    <span class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gold-100 text-gold-700 text-xl font-bold">
                        {{ $user->initials() }}
                    </span>
                @endif
                <div>
                    <p class="font-semibold text-ink">{{ $user->name }}</p>
                    <p class="text-sm text-ink-faint">{{ $user->email }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('account.profile.update') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <label for="photo" class="block text-sm font-medium text-ink-muted">Upload a new photo</label>
                <input
                    type="file" id="photo" name="photo" accept="image/png,image/jpeg,image/webp" required
                    class="block w-full text-sm text-ink-muted file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-brand-700 file:text-white file:text-sm file:font-medium hover:file:bg-brand-800 file:cursor-pointer"
                >
                @error('photo')
                    <p class="text-xs text-danger">{{ $message }}</p>
                @enderror
                <p class="text-xs text-ink-faint">JPG, PNG or WEBP, up to 2MB.</p>

                <button type="submit" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors shadow-sm">
                    Save Photo
                </button>
            </form>

            @if ($user->profile_photo_path)
                <form method="POST" action="{{ route('account.profile.destroy') }}" class="mt-3" onsubmit="return confirm('Remove your profile photo?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs text-ink-faint hover:text-danger transition-colors">
                        Remove photo
                    </button>
                </form>
            @endif
        </div>

        <div class="bg-panel border border-border rounded-xl shadow-sm p-6 max-w-lg mt-6">
            <h2 class="font-semibold text-ink mb-1">Nawiri Phone Number</h2>
            <p class="text-sm text-ink-faint mb-4">
                The phone number registered to your own Nawiri wallet - transport and airtime facilitation payments are sent here.
            </p>

            <form method="POST" action="{{ route('account.phone.update') }}" class="space-y-3">
                @csrf
                <label for="phone_number" class="block text-sm font-medium text-ink-muted">Phone number</label>
                <input
                    type="tel" id="phone_number" name="phone_number" placeholder="0712345678"
                    value="{{ old('phone_number', $user->phone_number) }}"
                    class="w-full rounded-lg border border-border px-3 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus:ring-2 focus:ring-brand-600/30 focus:border-brand-600"
                >
                @error('phone_number')
                    <p class="text-xs text-danger">{{ $message }}</p>
                @enderror

                <button type="submit" class="px-5 py-2.5 rounded-lg bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold transition-colors shadow-sm">
                    Save Phone Number
                </button>
            </form>
        </div>
</x-layout>
