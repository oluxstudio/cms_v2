<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Abel&family=Afacad:ital,wght@0,400..700;1,400..700&family=Aladin&family=Baumans&family=Bellota:ital,wght@0,300;0,400;0,700;1,300;1,400;1,700&family=Cantarell:ital,wght@0,400;0,700;1,400;1,700&family=Fjord+One&family=MuseoModerno:ital,wght@0,100..900;1,100..900&family=Text+Me+One&display=swap" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
