<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | DeliveryHub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-950 text-white flex items-center justify-center p-6">
    <main class="w-full max-w-md rounded-2xl bg-slate-900 p-8 shadow-xl">
        <h1 class="text-3xl font-bold mb-2">DeliveryHub</h1>
        <p class="text-slate-400 mb-8">Sign in to your workspace</p>

        @if ($errors->any())
            <div class="mb-5 rounded-lg bg-red-950 p-4 text-red-200" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="block mb-2">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required
                    autocomplete="email" class="w-full rounded-lg bg-slate-800 p-3">
            </div>

            <div>
                <label for="password" class="block mb-2">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password"
                    class="w-full rounded-lg bg-slate-800 p-3">
            </div>

            <button type="submit" class="w-full rounded-lg bg-blue-600 py-3 font-semibold hover:bg-blue-500">
                Sign In
            </button>
        </form>
    </main>
</body>

</html>
