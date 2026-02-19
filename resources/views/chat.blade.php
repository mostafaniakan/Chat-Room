<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Secure Direct Chat</title>
        @php
            $reverbRuntimeConfig = [
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('reverb.public.host', request()->getHost()),
                'port' => (int) config('reverb.public.port', request()->getPort()),
                'scheme' => config('reverb.public.scheme', request()->getScheme()),
            ];
        @endphp
        <script>
            window.__CHAT_REVERB_CONFIG__ = {{ \Illuminate\Support\Js::from($reverbRuntimeConfig) }};
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100">
        <div class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-4 py-8 md:px-8">
            <header class="mb-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-5 shadow-xl md:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.28em] text-emerald-300">Secure Private Messaging</p>
                        <h1 class="mt-2 text-2xl font-semibold text-white md:text-3xl">Direct Chat Room</h1>
                        <p class="mt-2 text-sm text-slate-300">
                            Logged in as
                            <span class="font-semibold text-emerald-300">{{ $authUser->username }}</span>
                            (this is your unique ID).
                        </p>
                        <p class="mt-1 text-xs text-amber-200">Messages auto-delete after 10 minutes.</p>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-700"
                        >
                            Logout
                        </button>
                    </form>
                </div>
            </header>

            <section
                id="chat-app"
                class="flex flex-1 flex-col overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60 shadow-2xl"
                data-auth-username="{{ $authUser->username }}"
                data-messages='@json($messages)'
                data-post-url="{{ route('messages.store') }}"
                data-find-url="{{ route('users.find') }}"
            >
                <div class="border-b border-slate-800 px-4 py-3 md:px-5">
                    <span
                        id="connectionStatus"
                        class="inline-flex items-center gap-2 rounded-full bg-amber-500/20 px-3 py-1 text-xs font-medium text-amber-200"
                    >
                        <span class="inline-block h-2 w-2 rounded-full bg-amber-300"></span>
                        Connecting to realtime server...
                    </span>
                </div>

                <div id="messages" class="flex-1 space-y-3 overflow-y-auto px-4 py-4 md:px-5 md:py-5"></div>

                <form id="chatForm" class="space-y-3 border-t border-slate-800 p-4 md:p-5" enctype="multipart/form-data">
                    <div class="grid gap-3 md:grid-cols-[1fr_auto]">
                        <label>
                            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-300">
                                Recipient ID (username)
                            </span>
                            <input
                                id="recipientId"
                                name="recipient_id"
                                type="text"
                                maxlength="30"
                                placeholder="user_id"
                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-400 focus:outline-none"
                                required
                            >
                        </label>

                        <button
                            type="button"
                            id="findRecipient"
                            class="self-end rounded-lg border border-cyan-500/40 bg-cyan-500/20 px-3 py-2 text-sm font-medium text-cyan-100 transition hover:bg-cyan-500/30"
                        >
                            Find ID
                        </button>
                    </div>

                    <p id="recipientState" class="text-xs text-slate-300">Enter an ID and click Find ID.</p>

                    <label class="block">
                        <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-300">Message</span>
                        <textarea
                            id="messageInput"
                            name="message"
                            rows="2"
                            maxlength="4000"
                            placeholder="Type your private message..."
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-400 focus:outline-none"
                        ></textarea>
                    </label>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            id="startRecord"
                            class="rounded-lg border border-rose-500/40 bg-rose-500/20 px-3 py-2 text-sm font-medium text-rose-100 transition hover:bg-rose-500/30"
                        >
                            Start Voice
                        </button>
                        <button
                            type="button"
                            id="stopRecord"
                            class="rounded-lg border border-amber-500/40 bg-amber-500/20 px-3 py-2 text-sm font-medium text-amber-100 transition hover:bg-amber-500/30 disabled:cursor-not-allowed disabled:opacity-50"
                            disabled
                        >
                            Stop Voice
                        </button>
                        <button
                            type="button"
                            id="clearVoice"
                            class="hidden rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-700"
                        >
                            Remove Voice
                        </button>
                        <span id="voiceState" class="text-xs text-slate-300">No voice selected.</span>
                        <audio id="voicePreview" class="hidden h-8" controls></audio>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <p id="formError" class="hidden text-sm text-rose-300"></p>
                        <button
                            type="submit"
                            id="sendMessage"
                            class="ml-auto rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-emerald-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Send Private Message
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </body>
</html>
