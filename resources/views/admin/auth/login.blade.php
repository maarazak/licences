<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-900 antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Backoffice — Licences</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="h-full flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8 space-y-6">
        <div class="text-center space-y-2">
            <div class="size-12 rounded-xl bg-teal-600 text-white font-bold text-2xl flex items-center justify-center mx-auto shadow-md">
                L
            </div>
            <h1 class="text-xl font-bold text-slate-800">Backoffice Administrateur</h1>
            <p class="text-xs text-slate-500">Gestion des commandes, stocks et licences</p>
        </div>

        @if($errors->any())
            <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('admin.login') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Adresse e-mail</label>
                <input type="email" id="email" name="email" value="{{ old('email', 'abdoulrazakmaali@gmail.com') }}" required autofocus class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition">
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Mot de passe</label>
                <input type="password" id="password" name="password" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition" placeholder="••••••••">
            </div>

            <button type="submit" class="w-full py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-semibold text-sm rounded-xl shadow-md transition duration-200">
                Se connecter
            </button>
        </form>

        <div class="text-center">
            <a href="http://localhost:3001" class="text-xs font-medium text-slate-500 hover:text-teal-600 transition">
                ← Retour au site public
            </a>
        </div>
    </div>
</body>
</html>
