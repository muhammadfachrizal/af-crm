<div class="crm-content">
    <x-mary-header title="{{ $locationId ? 'Ubah' : 'Tambah' }} Lokasi Kerja" class="mb-5" progress-indicator>
        <x-slot:actions>
            <x-mary-button label="Kembali" link="{{ url(route('laravel-crm.work-locations.index')) }}" icon="fas.angle-double-left" class="btn-sm" responsive />
        </x-slot:actions>
    </x-mary-header>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

    <x-mary-form wire:submit="save">
        <div class="mb-4">
            <label class="label"><span class="label-text font-semibold">Klik di peta untuk memilih titik lokasi kerja</span></label>
            <div wire:ignore><div id="wl-map" style="height: 400px; border-radius: 0.5rem;"></div></div>
            @error('latitude') <p class="text-error text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4 mb-2">
            <x-mary-input label="Latitude" wire:model="latitude" />
            <x-mary-input label="Longitude" wire:model="longitude" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <x-mary-input label="Nama lokasi" wire:model="name" placeholder="mis. Kantor Pusat" />
            <x-mary-input label="Kode" wire:model="code" />
        </div>
        <x-mary-input label="Alamat" wire:model="address" placeholder="Terisi otomatis saat klik peta, atau isi manual" />
        <x-mary-input label="Radius (meter)" type="number" wire:model="radius_meters" hint="Jarak maksimum dari titik agar absensi dianggap di lokasi" />
        <x-mary-toggle label="Aktif" wire:model="is_active" />

        <x-slot:actions>
            <x-mary-button label="Batal" link="{{ url(route('laravel-crm.work-locations.index')) }}" />
            <x-mary-button label="Simpan" class="btn-primary text-white" type="submit" spinner="save" />
        </x-slot:actions>
    </x-mary-form>

    <script>
        document.addEventListener('livewire:navigated', initWlMap);
        document.addEventListener('DOMContentLoaded', initWlMap);

        function initWlMap() {
            const el = document.getElementById('wl-map');
            if (!el || el._leaflet_id) return;

            const lat0 = parseFloat(@js($latitude));
            const lng0 = parseFloat(@js($longitude));
            const has = !isNaN(lat0) && !isNaN(lng0);

            const map = L.map('wl-map').setView(has ? [lat0, lng0] : [-6.2088, 106.8456], has ? 16 : 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
            }).addTo(map);

            let marker = has ? L.marker([lat0, lng0]).addTo(map) : null;
            let circle = null;

            const drawCircle = (latlng) => {
                const r = parseInt(@this.get('radius_meters')) || 100;
                if (circle) map.removeLayer(circle);
                circle = L.circle(latlng, { radius: r }).addTo(map);
            };
            if (has) drawCircle([lat0, lng0]);

            map.on('click', (e) => {
                marker ? marker.setLatLng(e.latlng) : (marker = L.marker(e.latlng).addTo(map));
                drawCircle(e.latlng);
                @this.set('latitude', e.latlng.lat.toFixed(7));
                @this.set('longitude', e.latlng.lng.toFixed(7));

                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${e.latlng.lat}&lon=${e.latlng.lng}`)
                    .then(r => r.json())
                    .then(d => { if (d && d.display_name && !@this.get('address')) @this.set('address', d.display_name); })
                    .catch(() => {});
            });

            if (!has && navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(p => map.setView([p.coords.latitude, p.coords.longitude], 15));
            }
        }
    </script>
</div>
