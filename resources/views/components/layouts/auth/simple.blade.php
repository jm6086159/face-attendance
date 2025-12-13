<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-black antialiased" style="background: linear-gradient(115deg, rgba(6,18,49,.85), rgba(8,61,33,.8)), url('/images/campus.jpg') center/cover no-repeat;">
        <div class="flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-6 rounded-2xl bg-white/5 p-6 shadow-2xl ring-1 ring-white/10 backdrop-blur">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 text-center" wire:navigate>
                    <img src="/images/logo.png" alt="FRAS Logo" class="h-16 w-16 object-contain drop-shadow-lg">
                    <div class="leading-tight text-white">
                        <div class="text-sm font-semibold tracking-[0.28em]">ST. FRANCIS XAVIER COLLEGE</div>
                        <div class="text-xs tracking-[0.35em] text-emerald-200">SAN FRANCISCO • AGUSAN DEL SUR</div>
                    </div>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>

