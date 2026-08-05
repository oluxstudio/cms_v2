<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ucwords(str_replace('-', ' ', $site->name)) }} — coming soon</title>
    <style>
        body { margin:0; font-family:ui-sans-serif,system-ui,sans-serif; background:#0c1a3e; color:#fff;
               min-height:100vh; display:flex; align-items:center; justify-content:center; text-align:center; }
        h1 { font-size:2rem; font-weight:800; margin:0 0 .5rem; }
        p  { color:rgba(255,255,255,.6); font-size:.95rem; }
    </style>
</head>
<body>
    <div>
        <h1>{{ ucwords(str_replace('-', ' ', $site->name)) }}</h1>
        <p>This site is live and being prepared — check back shortly.</p>
    </div>
</body>
</html>
