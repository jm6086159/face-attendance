<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

@php($faviconVersion = file_exists(public_path('favicon.png')) ? filemtime(public_path('favicon.png')) : time())
<link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v={{ $faviconVersion }}">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
