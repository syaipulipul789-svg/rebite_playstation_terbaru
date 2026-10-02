<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">

    <title>Unit tidak ditemukan — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-950 text-slate-200 antialiased">

    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-5 py-10 text-center">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-500/15 text-slate-400 ring-1 ring-inset ring-slate-500/30">
            <x-icon name="circle-alert" class="h-7 w-7" />
        </span>

        <h1 class="mt-5 text-xl font-extrabold text-white">Unit tidak ditemukan</h1>

        <p class="mt-3 text-sm leading-relaxed text-slate-400">
            Kode unit <span class="font-mono font-bold text-slate-300">{{ $unitCode }}</span>
            tidak terdaftar. Periksa lagi QR di meja, atau hubungi kasir.
        </p>

        <a href="{{ route('customer.home') }}" class="btn-ghost mt-6">
            <x-icon name="arrow-left-right" class="h-4 w-4" />
            Halaman utama
        </a>
    </main>

</body>
</html>
