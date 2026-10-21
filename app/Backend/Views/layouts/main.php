<?php /** @var string $content */ /** @var string|null $title */ ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-url" content="<?= e(url('/')) ?>">
    <title><?= e(($title ?? 'App') . ' · ' . Core\Config::get('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('build/css/app.css')) ?>">
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-slate-900 text-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
            <a href="<?= e(url('/admin')) ?>" class="font-semibold">Admin · <?= e(Core\Config::get('app.name')) ?></a>
            <a href="<?= e(url('/')) ?>" class="text-xs text-slate-300 hover:text-white">View site</a>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-6 py-10">
        <?= $content ?>
    </main>

    <script type="module" src="<?= e(asset('build/js/app.js')) ?>"></script>
</body>
</html>
