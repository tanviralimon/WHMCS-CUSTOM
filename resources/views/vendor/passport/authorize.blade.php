<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authorize {{ $client->name }} — Orcus Technology</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    </style>
</head>
<body class="min-h-screen flex bg-slate-50">

    {{-- Left - Branding --}}
    <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-slate-900 via-blue-900 to-indigo-900 items-center justify-center p-12 relative overflow-hidden">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-20 left-20 w-72 h-72 bg-blue-500 rounded-full blur-3xl"></div>
            <div class="absolute bottom-20 right-20 w-96 h-96 bg-purple-500 rounded-full blur-3xl"></div>
        </div>
        <div class="relative z-10 text-center max-w-md">
            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-8">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <h1 class="text-4xl font-bold text-white mb-4">Orcus Technology</h1>
            <p class="text-blue-200 text-lg leading-relaxed">One Portal, All Services</p>
            <p class="text-blue-300/60 text-sm mt-2">Way to Automation</p>
        </div>
    </div>

    {{-- Right - Authorization Form --}}
    <div class="flex-1 flex items-center justify-center p-6">
        <div class="w-full max-w-md">

            {{-- Mobile logo --}}
            <div class="lg:hidden text-center mb-8">
                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-slate-900">Orcus Technology</h1>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">

                {{-- Header --}}
                <div class="text-center mb-6">
                    <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-slate-900 mb-1">Authorization Request</h2>
                    <p class="text-slate-500 text-sm">
                        <strong class="text-slate-700">{{ $client->name }}</strong> wants to access your account
                    </p>
                </div>

                {{-- Signed-in user --}}
                <div class="bg-slate-50 rounded-xl p-4 mb-6 flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-900">{{ $user->name }}</p>
                        <p class="text-xs text-slate-500">{{ $user->email }}</p>
                    </div>
                </div>

                {{-- Scopes --}}
                @if (count($scopes) > 0)
                    <div class="mb-6">
                        <p class="text-sm font-medium text-slate-700 mb-3">This application will be able to:</p>
                        <ul class="space-y-2">
                            @foreach ($scopes as $scope)
                                <li class="flex items-center gap-3 text-sm text-slate-600">
                                    <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ $scope->description }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Buttons --}}
                <div class="flex gap-3">
                    {{-- Deny --}}
                    <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="state"    value="{{ $request->state }}">
                        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit"
                                class="w-full py-2.5 px-4 rounded-xl border border-slate-300 text-slate-700 font-medium text-sm
                                       hover:bg-slate-50 transition-colors cursor-pointer">
                            Cancel
                        </button>
                    </form>

                    {{-- Approve --}}
                    <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="state"    value="{{ $request->state }}">
                        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit"
                                class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600
                                       text-white font-medium text-sm hover:from-blue-700 hover:to-indigo-700
                                       transition-all cursor-pointer">
                            Authorize
                        </button>
                    </form>
                </div>

                {{-- Footer note --}}
                <p class="text-xs text-slate-400 text-center mt-6">
                    By authorizing, you allow this application to access the listed information.
                    You can revoke access at any time from your account settings.
                </p>
            </div>

            <p class="text-center text-xs text-slate-400 mt-6">
                &copy; {{ date('Y') }} Orcus Technology &middot; orcus.one
            </p>
        </div>
    </div>

</body>
</html>
