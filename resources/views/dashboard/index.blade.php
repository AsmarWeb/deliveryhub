<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | DeliveryHub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-950 text-white">
    <header class="border-b border-slate-800 p-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">DeliveryHub</h1>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-lg bg-slate-800 px-5 py-2 hover:bg-slate-700">
                Logout
            </button>
        </form>
    </header>

    <main class="max-w-6xl mx-auto p-8">
        <h2 class="text-3xl font-bold mb-2">
            Welcome, {{ auth()->user()->name }}
        </h2>

        <p class="text-slate-400 mb-8">
            Your DeliveryHub workspace
        </p>

        <section class="rounded-xl border border-slate-800 bg-slate-900 p-6">
            <h3 class="font-semibold text-xl mb-3">
                Dashboard setup complete
            </h3>
            <p class="text-slate-400">
                Your workspace is protected by Laravel authentication.
                Operational statistics will be connected next.
            </p>
        </section>
    </main>
</body>

</html>
