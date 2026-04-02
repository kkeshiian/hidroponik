@extends('layouts.app')

@section('content')
<div class="w-full py-8 px-6">
    
    <!-- Database Statistics -->
    <div class="bg-white shadow-sm rounded-lg p-4 md:p-8 mb-6">
        <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4 md:mb-6">Statistik Database</h2>
        
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
            <div class="rounded-xl p-4 md:p-6 bg-gradient-to-br from-green-50 to-green-100 shadow-sm flex flex-col justify-between border border-green-200 h-full">
                <div class="text-xs md:text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Total Data</div>
                <div class="text-xl md:text-3xl font-bold text-green-700">{{ number_format($stats['total_records'] ?? 0) }}</div>
            </div>
            
            <div class="rounded-xl p-4 md:p-6 bg-gradient-to-br from-green-50 to-green-100 shadow-sm flex flex-col justify-between border border-green-200 h-full">
                <div class="text-xs md:text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Ukuran Data</div>
                <div class="text-xl md:text-3xl font-bold text-green-700">{{ $stats['db_size'] ?? 'N/A' }}</div>
            </div>
            
            <div class="rounded-xl p-4 md:p-6 bg-gradient-to-br from-green-50 to-green-100 shadow-sm flex flex-col justify-between border border-green-200 h-full">
                <div class="text-xs md:text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Data Terlama</div>
                <div class="text-sm md:text-lg font-bold text-green-700 leading-tight">
                    @if($stats['oldest_record'])
                        <div class="whitespace-nowrap">{{ date('d/m/Y', strtotime($stats['oldest_record'])) }}</div>
                        <div class="text-xs md:text-sm font-medium opacity-80">{{ date('H:i', strtotime($stats['oldest_record'])) }}</div>
                    @else
                        -
                    @endif
                </div>
            </div>
            
            <div class="rounded-xl p-4 md:p-6 bg-gradient-to-br from-green-50 to-green-100 shadow-sm flex flex-col justify-between border border-green-200 h-full">
                <div class="text-xs md:text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Data Terbaru</div>
                <div class="text-sm md:text-lg font-bold text-green-700 leading-tight">
                    @if($stats['newest_record'])
                        <div class="whitespace-nowrap">{{ date('d/m/Y', strtotime($stats['newest_record'])) }}</div>
                        <div class="text-xs md:text-sm font-medium opacity-80">{{ date('H:i', strtotime($stats['newest_record'])) }}</div>
                    @else
                        -
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Save Interval Settings -->
    <div class="bg-white shadow-sm rounded-lg p-4 md:p-8 mb-6">
        <div class="flex items-center gap-3 mb-6">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h2 class="text-xl md:text-2xl font-bold text-gray-800">Interval Penyimpanan Data</h2>
        </div>
        
        <p class="text-gray-600 mb-6">Atur seberapa sering data dari MQTT disimpan ke database MySQL</p>
        
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
            <button onclick="setSaveInterval('realtime')" class="interval-btn px-4 py-4 rounded-xl border-2 transition-all text-sm font-medium {{ $interval == 'realtime' ? 'bg-green-600 text-white border-green-600 shadow-md' : 'bg-white text-gray-700 border-gray-200 hover:border-green-500 hover:shadow-sm' }}">
                <div class="font-bold text-base mb-1">Realtime</div>
                <div class="text-xs opacity-90">Setiap data masuk</div>
            </button>
            
            <button onclick="setSaveInterval('5')" class="interval-btn px-4 py-4 rounded-xl border-2 transition-all text-sm font-medium {{ $interval == '5' ? 'bg-green-600 text-white border-green-600 shadow-md' : 'bg-white text-gray-700 border-gray-200 hover:border-green-500 hover:shadow-sm' }}">
                <div class="font-bold text-base mb-1">Per 5 Menit</div>
                <div class="text-xs opacity-90">12x per jam</div>
            </button>
            
            <button onclick="setSaveInterval('10')" class="interval-btn px-4 py-4 rounded-xl border-2 transition-all text-sm font-medium {{ $interval == '10' ? 'bg-green-600 text-white border-green-600 shadow-md' : 'bg-white text-gray-700 border-gray-200 hover:border-green-500 hover:shadow-sm' }}">
                <div class="font-bold text-base mb-1">Per 10 Menit</div>
                <div class="text-xs opacity-90">6x per jam</div>
            </button>
            
            <button onclick="setSaveInterval('15')" class="interval-btn px-4 py-4 rounded-xl border-2 transition-all text-sm font-medium {{ $interval == '15' ? 'bg-green-600 text-white border-green-600 shadow-md' : 'bg-white text-gray-700 border-gray-200 hover:border-green-500 hover:shadow-sm' }}">
                <div class="font-bold text-base mb-1">Per 15 Menit</div>
                <div class="text-xs opacity-90">4x per jam</div>
            </button>
            
            <button onclick="setSaveInterval('30')" class="interval-btn px-4 py-4 rounded-xl border-2 transition-all text-sm font-medium {{ $interval == '30' ? 'bg-green-600 text-white border-green-600 shadow-md' : 'bg-white text-gray-700 border-gray-200 hover:border-green-500 hover:shadow-sm' }}">
                <div class="font-bold text-base mb-1">Per 30 Menit</div>
                <div class="text-xs opacity-90">2x per jam</div>
            </button>
            
            <button onclick="setSaveInterval('60')" class="interval-btn px-4 py-4 rounded-xl border-2 transition-all text-sm font-medium {{ $interval == '60' ? 'bg-green-600 text-white border-green-600 shadow-md' : 'bg-white text-gray-700 border-gray-200 hover:border-green-500 hover:shadow-sm' }}">
                <div class="font-bold text-base mb-1">Per 1 Jam</div>
                <div class="text-xs opacity-90">24x per hari</div>
            </button>
            
            <button onclick="setSaveInterval('720')" class="interval-btn px-4 py-4 rounded-xl border-2 transition-all text-sm font-medium {{ $interval == '720' ? 'bg-green-600 text-white border-green-600 shadow-md' : 'bg-white text-gray-700 border-gray-200 hover:border-green-500 hover:shadow-sm' }}">
                <div class="font-bold text-base mb-1">Per 12 Jam</div>
                <div class="text-xs opacity-90">2x per hari</div>
            </button>
            
            <button onclick="setSaveInterval('1440')" class="interval-btn px-4 py-4 rounded-xl border-2 transition-all text-sm font-medium {{ $interval == '1440' ? 'bg-green-600 text-white border-green-600 shadow-md' : 'bg-white text-gray-700 border-gray-200 hover:border-green-500 hover:shadow-sm' }}">
                <div class="font-bold text-base mb-1">Per 1 Hari</div>
                <div class="text-xs opacity-90">1x per hari</div>
            </button>
        </div>
        
        <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-start gap-2">
                <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="text-sm text-gray-700">
                    <strong>Catatan:</strong> Pengaturan interval akan mempengaruhi berapa banyak data yang disimpan ke database. 
                    Interval yang lebih panjang akan menghemat ruang penyimpanan tetapi mengurangi detail data historis.
                </div>
            </div>
        </div>
    </div>

    <!-- Data Management -->
    <div class="bg-white shadow-sm rounded-lg p-4 md:p-8">
        <div class="flex items-center gap-3 mb-6">
            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
            </svg>
            <h2 class="text-xl md:text-2xl font-bold text-gray-800">Manajemen Data</h2>
        </div>
        
        <p class="text-gray-600 mb-6">Hapus data lama untuk menghemat ruang penyimpanan database</p>
        
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
            <button onclick="deleteData('1week')" class="delete-btn px-4 py-4 rounded-xl border-2 border-gray-200 bg-white text-gray-700 hover:border-red-500 hover:bg-red-50 hover:shadow-sm transition-all text-sm font-medium">
                <div class="font-bold text-base mb-1">Hapus > 1 Minggu</div>
                <div class="text-xs opacity-80">Data lebih dari 1 minggu</div>
            </button>
            
            <button onclick="deleteData('2weeks')" class="delete-btn px-4 py-4 rounded-xl border-2 border-gray-200 bg-white text-gray-700 hover:border-red-500 hover:bg-red-50 hover:shadow-sm transition-all text-sm font-medium">
                <div class="font-bold text-base mb-1">Hapus > 2 Minggu</div>
                <div class="text-xs opacity-80">Data lebih dari 2 minggu</div>
            </button>
            
            <button onclick="deleteData('1month')" class="delete-btn px-4 py-4 rounded-xl border-2 border-gray-200 bg-white text-gray-700 hover:border-red-500 hover:bg-red-50 hover:shadow-sm transition-all text-sm font-medium">
                <div class="font-bold text-base mb-1">Hapus > 1 Bulan</div>
                <div class="text-xs opacity-80">Data lebih dari 1 bulan</div>
            </button>
            
            <button onclick="deleteData('3months')" class="delete-btn px-4 py-4 rounded-xl border-2 border-gray-200 bg-white text-gray-700 hover:border-red-500 hover:bg-red-50 hover:shadow-sm transition-all text-sm font-medium">
                <div class="font-bold text-base mb-1">Hapus > 3 Bulan</div>
                <div class="text-xs opacity-80">Data lebih dari 3 bulan</div>
            </button>
            
            <button onclick="deleteData('6months')" class="delete-btn px-4 py-4 rounded-xl border-2 border-gray-200 bg-white text-gray-700 hover:border-red-500 hover:bg-red-50 hover:shadow-sm transition-all text-sm font-medium">
                <div class="font-bold text-base mb-1">Hapus > 6 Bulan</div>
                <div class="text-xs opacity-80">Data lebih dari 6 bulan</div>
            </button>
            
            <button onclick="deleteData('1year')" class="delete-btn px-4 py-4 rounded-xl border-2 border-gray-200 bg-white text-gray-700 hover:border-red-500 hover:bg-red-50 hover:shadow-sm transition-all text-sm font-medium">
                <div class="font-bold text-base mb-1">Hapus > 1 Tahun</div>
                <div class="text-xs opacity-80">Data lebih dari 1 tahun</div>
            </button>
            
            <button onclick="deleteData('all')" class="delete-btn px-4 py-4 rounded-xl border-2 border-red-400 bg-red-50 text-red-700 hover:border-red-600 hover:bg-red-100 hover:shadow-md transition-all text-sm font-medium">
                <div class="font-bold text-base mb-1">Hapus Semua</div>
                <div class="text-xs opacity-80">⚠️ Hapus semua data</div>
            </button>
        </div>
        
        <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-start gap-2">
                <svg class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div class="text-sm text-red-700">
                    <strong>Peringatan:</strong> Penghapusan data bersifat permanen dan tidak dapat dikembalikan. 
                    Pastikan Anda telah melakukan backup data jika diperlukan.
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
async function setSaveInterval(interval) {
    try {
        const response = await fetch('/pengaturan/interval', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ interval: interval })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: data.message,
                confirmButtonColor: '#16a34a'
            });
            location.reload();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: error.message,
            confirmButtonColor: '#16a34a'
        });
    }
}

async function deleteData(period) {
    const periodNames = {
        '1week': 'data lebih dari 1 minggu',
        '2weeks': 'data lebih dari 2 minggu',
        '1month': 'data lebih dari 1 bulan',
        '3months': 'data lebih dari 3 bulan',
        '6months': 'data lebih dari 6 bulan',
        '1year': 'data lebih dari 1 tahun',
        'all': 'SEMUA data'
    };
    
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Konfirmasi Penghapusan',
        html: `Apakah Anda yakin ingin menghapus <strong>${periodNames[period]}</strong>?<br><br><span class="text-red-600">Tindakan ini tidak dapat dibatalkan!</span>`,
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    });
    
    if (result.isConfirmed) {
        try {
            const response = await fetch('/pengaturan/delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ period: period })
            });
            
            // Check if response is ok
            if (!response.ok) {
                const text = await response.text();
                console.error('Response error:', text);
                throw new Error(`Server error: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: data.message,
                    confirmButtonColor: '#16a34a'
                });
                location.reload();
            } else {
                throw new Error(data.message || 'Terjadi kesalahan');
            }
        } catch (error) {
            console.error('Delete error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: error.message || 'Terjadi kesalahan saat menghapus data',
                confirmButtonColor: '#16a34a'
            });
        }
    }
}
</script>

@endsection
