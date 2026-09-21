<div class="crm-content">
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.create')) }} {{ __('laravel-crm::lang.visit') }}" class="mb-5" progress-indicator>
        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.back')) }}" link="{{ url(route('laravel-crm.visits.index')) }}" icon="fas.angle-double-left" class="btn-sm" responsive />
        </x-slot:actions>
    </x-mary-header>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

    <x-mary-form wire:submit="save">
        <div class="mb-4">
            <label class="label"><span class="label-text font-semibold">Klik di peta untuk memilih lokasi tujuan visit</span></label>

            {{-- wire:ignore supaya Livewire gak nge-reset peta tiap kali ada perubahan state lain --}}
            <div wire:ignore>
                <div id="visit-map" style="height: 400px; border-radius: 0.5rem;"></div>
            </div>

            @error('target_latitude') <p class="text-error text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <x-mary-input label="Latitude" wire:model="target_latitude" readonly />
            <x-mary-input label="Longitude" wire:model="target_longitude" readonly />
        </div>

        <x-mary-input label="{{ ucfirst(__('laravel-crm::lang.title')) }}" wire:model="title" placeholder="mis. Kunjungan ke Toko Ali" />
        <x-mary-input label="{{ ucfirst(__('laravel-crm::lang.location')) }}" wire:model="target_address" placeholder="Terisi otomatis pas klik peta, atau isi manual" />

        <div class="grid grid-cols-2 gap-4">
            <x-mary-input label="Radius (meter)" wire:model="target_radius_meters" type="number" hint="Sales wajib dalam radius ini pas check-in" />
            <x-mary-input label="{{ ucfirst(__('laravel-crm::lang.date')) }} Rencana" wire:model="scheduled_at" type="datetime-local" />
        </div>

        @if($this->canAssignOthers())
            <x-mary-select label="Assign ke" wire:model="assign_user_id" :options="$this->users()" option-label="name" option-value="id" hint="Kosongkan/pilih diri sendiri kalau visit ini buat kamu sendiri" />
        @endif

        <x-mary-textarea label="{{ ucfirst(__('laravel-crm::lang.notes')) }}" wire:model="notes" />

        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.cancel')) }}" class="btn" link="{{ url(route('laravel-crm.visits.index')) }}" />
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.save')) }}" class="btn-primary text-white" type="submit" spinner="save" />
        </x-slot:actions>
    </x-mary-form>

    <script>
        document.addEventListener('livewire:navigated', initVisitMap);
        document.addEventListener('DOMContentLoaded', initVisitMap);

        function initVisitMap() {
            const mapEl = document.getElementById('visit-map');
            if (!mapEl || mapEl._leaflet_id) return; // sudah diinisialisasi, skip

            const map = L.map('visit-map').setView([-6.2088, 106.8456], 12); // default: Jakarta

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);

            let marker = null;

            map.on('click', function (e) {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;

                if (marker) {
                    marker.setLatLng(e.latlng);
                } else {
                    marker = L.marker(e.latlng).addTo(map);
                }

                @this.set('target_latitude', lat.toFixed(7));
                @this.set('target_longitude', lng.toFixed(7));

                // Reverse geocode pakai Nominatim (OpenStreetMap), gratis tanpa API key
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                    .then((res) => res.json())
                    .then((data) => {
                        if (data && data.display_name) {
                            @this.set('target_address', data.display_name);
                        }
                    })
                    .catch(() => {
                        // Gagal reverse geocode gapapa, lat/long tetap tersimpan
                    });
            });

            // Coba pusatkan peta ke lokasi user kalau browser mengizinkan
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((pos) => {
                    map.setView([pos.coords.latitude, pos.coords.longitude], 14);
                });
            }
        }
    </script>
</div>
