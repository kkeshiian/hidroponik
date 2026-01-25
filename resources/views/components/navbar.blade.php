<nav class="navbar bg-white shadow-md sticky top-0 z-50 px-4 py-3 flex items-center justify-between">


    <!-- LEFT : LOGO -->
    <div class="flex items-center gap-4 flex-none">
        <img src="/img/logo.png" alt="Logo" class="h-10">

        <div class="hidden md:block">
            <h1 class="text-xl font-bold text-green-700 leading-tight">Hidroganik Alfa</h1>
            <p class="text-xs text-gray-500 mt-0">Smart Hydroponic System</p>
        </div>
    </div>

    <!-- CENTER : MENU (RATA TENGAH) -->
    <div class="flex-1 hidden md:flex justify-center gap-8 mr-20">
        <a href="{{ route('home') }}"
           class="px-2 py-1 border-b-2 transition-all
           {{ request()->routeIs('home')
                ? 'border-green-600 text-green-700 font-semibold'
                : 'border-transparent text-gray-700 hover:text-green-600' }}">
            Dashboard
        </a>

        <a href="{{ route('log') }}"
           class="px-2 py-1 border-b-2 transition-all
           {{ request()->routeIs('log')
                ? 'border-green-600 text-green-700 font-semibold'
                : 'border-transparent text-gray-700 hover:text-green-600' }}">
            Log Data
        </a>

        <a href="{{ url('/kalibrasi') }}"
           class="px-2 py-1 border-b-2 transition-all
           {{ request()->is('kalibrasi')
                ? 'border-green-600 text-green-700 font-semibold'
                : 'border-transparent text-gray-700 hover:text-green-600' }}">
            Kalibrasi
        </a>

        <a href="{{ url('/pengaturan') }}"
           class="px-2 py-1 border-b-2 transition-all
           {{ request()->is('pengaturan')
                ? 'border-green-600 text-green-700 font-semibold'
                : 'border-transparent text-gray-700 hover:text-green-600' }}">
            Pengaturan
        </a>

    </div>

    <!-- RIGHT : CLOCK (dipindah ke kiri hamburger) -->
<div class="flex items-center gap-3 flex-none ml-4 md:ml-0">

    <div class="text-right">
        <div id="clock" class="text-xl font-bold text-green-700">00.00.00</div>
        <div id="date" class="text-gray-500 text-sm">--</div>
    </div>

    <!-- MOBILE: Hamburger -->
    <div class="md:hidden">
        <div class="dropdown dropdown-end">
            <label tabindex="0" 
                class="btn bg-white border-none shadow-none p-3 rounded-xl">
                <svg xmlns="http://www.w3.org/2000/svg" 
                     class="h-8 w-8 text-green-700" 
                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" 
                          stroke-width="2" 
                          d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </label>

            <ul tabindex="0"
                class="dropdown-content menu bg-white rounded-xl p-4 mt-2 w-48 shadow-none border border-gray-200">
                <li><a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'text-green-700 font-bold' : '' }}">Dashboard</a></li>
                <li><a href="{{ route('log') }}" class="{{ request()->routeIs('log') ? 'text-green-700 font-bold' : '' }}">Log Data</a></li>
                <li><a href="{{ url('/kalibrasi') }}" class="{{ request()->is('kalibrasi') ? 'text-green-700 font-bold' : '' }}">Kalibrasi</a></li>
                <li><a href="{{ url('/pengaturan') }}" class="{{ request()->is('pengaturan') ? 'text-green-700 font-bold' : '' }}">Pengaturan</a></li>
            </ul>
        </div>
    </div>
</div>
</nav>

<!-- CLOCK SCRIPT -->
<script>
function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    const s = String(now.getSeconds()).padStart(2, '0');
    document.getElementById('clock').innerText = `${h}.${m}.${s}`;

    const options = { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' };
    document.getElementById('date').innerText = now.toLocaleDateString('id-ID', options);
}
setInterval(updateClock, 1000);
updateClock();
</script>
