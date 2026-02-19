<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Register - Secure Chat</title>
        @vite('resources/css/app.css')
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100">
        <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-4 py-10">
            <section class="w-full rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-2xl">
                <p class="text-xs uppercase tracking-[0.28em] text-emerald-300">Secure Chat</p>
                <h1 class="mt-2 text-2xl font-semibold text-white">Create Account</h1>
                <p class="mt-1 text-sm text-slate-300">Your username is your unique user ID.</p>

                @if ($errors->any())
                    <div class="mt-4 rounded-lg border border-rose-700/60 bg-rose-900/30 px-3 py-2 text-sm text-rose-200">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('register.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-300">
                            Username ID
                        </span>
                        <input
                            type="text"
                            name="username"
                            value="{{ old('username') }}"
                            maxlength="30"
                            required
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-400 focus:outline-none"
                            placeholder="letters, numbers, underscore"
                        >
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-300">Password</span>
                        <input
                            type="password"
                            name="password"
                            required
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-400 focus:outline-none"
                            placeholder="At least 8 characters"
                        >
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-300">
                            Confirm Password
                        </span>
                        <input
                            type="password"
                            name="password_confirmation"
                            required
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-400 focus:outline-none"
                            placeholder="Repeat your password"
                        >
                    </label>

                    <button
                        type="submit"
                        class="w-full rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-emerald-950 transition hover:bg-emerald-400"
                    >
                        Register
                    </button>
                </form>

                <p class="mt-4 text-center text-sm text-slate-300">
                    Already have an account?
                    <a href="{{ route('login') }}" class="font-medium text-emerald-300 hover:text-emerald-200">
                        Login
                    </a>
                </p>
            </section>
        </main>
    </body>
</html>
