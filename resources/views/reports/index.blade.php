<x-crm::app-layout title="Laporan">
    <div class="crm-content">
        <x-mary-header title="Laporan" subtitle="Penjualan, piutang, dan aktivitas lapangan - bisa diexport ke Excel, CSV, atau dicetak sebagai PDF" />

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($reports as $key => [$title, $desc])
                <a href="{{ url(route('laravel-crm.reports.show', $key)) }}" class="block">
                    <x-mary-card shadow class="hover:shadow-lg transition">
                        <div class="font-semibold text-lg">{{ $title }}</div>
                        <div class="text-sm opacity-70 mt-1">{{ $desc }}</div>
                    </x-mary-card>
                </a>
            @endforeach
            <a href="{{ url(route('laravel-crm.attendance-recap.index')) }}" class="block">
                <x-mary-card shadow class="hover:shadow-lg transition">
                    <div class="font-semibold text-lg">Rekap Absensi</div>
                    <div class="text-sm opacity-70 mt-1">Kehadiran, telat, lembur per bulan (Excel/CSV)</div>
                </x-mary-card>
            </a>
        </div>
    </div>
</x-crm::app-layout>
