<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-50 antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Accès administrateur — Licences</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
            },
            colors: {
              brand: {
                DEFAULT: '#0d5c4a',
                hover: '#094336',
                soft: '#e7f5f1',
                strong: '#0a4235',
              },
              accent: {
                DEFAULT: '#e85a07',
                hover: '#cf4f06',
              }
            }
          }
        }
      }
    </script>
</head>
<body class="min-h-full flex items-center justify-center px-4 py-12 text-slate-800">
    <div class="w-full max-w-sm">
        <div class="mb-6 flex justify-center">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 group">
                <span class="flex size-10 items-center justify-center rounded-xl bg-brand text-white font-bold text-xl shadow-sm transition group-hover:scale-105">
                    L
                </span>
                <span class="text-xl font-extrabold tracking-tight text-slate-900">
                    Licences<span class="text-accent">.</span>
                </span>
            </a>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/80 p-6 sm:p-8 shadow-[0_20px_40px_-15px_rgba(0,0,0,0.07)] space-y-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Accès administrateur</h1>
                <p class="mt-1.5 text-xs text-slate-500 leading-relaxed">
                    Réservé à l'équipe. Saisissez votre e-mail et votre mot de passe.
                </p>
            </div>

            @if($errors->any())
                <div class="p-3.5 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl flex items-start gap-2">
                    <svg class="size-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            @if(session('success'))
                <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs rounded-xl flex items-start gap-2">
                    <svg class="size-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if($step === 'code')
                <!-- Étape 2FA : Saisie du code à 6 chiffres -->
                <form action="{{ route('admin.2fa.verify') }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="flex items-start gap-2.5 rounded-xl bg-brand-soft p-3 text-xs leading-relaxed text-brand-strong">
                        <svg class="size-4 shrink-0 mt-0.5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span>
                            Mot de passe correct. Un code à 6 chiffres vient d'être envoyé à <strong class="break-all">{{ $email }}</strong>.
                        </span>
                    </div>

                    <div>
                        <label for="code" class="block text-xs font-semibold text-slate-700 mb-1.5">
                            Code de vérification
                        </label>
                        <input
                            type="text"
                            id="code"
                            name="code"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            autocomplete="one-time-code"
                            autofocus
                            required
                            placeholder="000000"
                            class="w-full px-3.5 py-3 rounded-xl border border-slate-300 text-center font-mono text-2xl tracking-[0.4em] focus:border-brand focus:ring-2 focus:ring-brand/20 outline-none transition"
                            oninput="this.value = this.value.replace(/\D/g, '').slice(0, 6)"
                        >
                        <p class="mt-1.5 text-[11px] text-slate-400">Valable 10 minutes, utilisable une seule fois.</p>
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 bg-brand hover:bg-brand-hover text-white font-semibold text-sm rounded-xl shadow-md transition duration-200 flex items-center justify-center gap-2"
                    >
                        <span>Valider</span>
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>

                    <div class="pt-2 text-center">
                        <a href="{{ route('admin.2fa.cancel') }}" class="text-xs font-medium text-slate-500 hover:text-slate-800 transition">
                            Recommencer la connexion
                        </a>
                    </div>
                </form>
            @else
                <!-- Étape 1 : Email + Mot de passe -->
                <form action="{{ route('admin.login') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-700 mb-1.5">Adresse e-mail</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email', $email) }}"
                            required
                            autofocus
                            autocomplete="username"
                            maxlength="191"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20 outline-none transition"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-semibold text-slate-700 mb-1.5">Mot de passe</label>
                        <div class="relative">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                maxlength="200"
                                class="w-full px-3.5 py-2.5 pr-11 rounded-xl border border-slate-300 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20 outline-none transition"
                            >
                            <button
                                type="button"
                                onclick="togglePassword()"
                                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 hover:text-slate-600 transition"
                                aria-label="Afficher ou masquer le mot de passe"
                            >
                                <svg id="eye-icon" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 bg-brand hover:bg-brand-hover text-white font-semibold text-sm rounded-xl shadow-md transition duration-200 flex items-center justify-center gap-2"
                    >
                        <span>Continuer</span>
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </form>
            @endif
        </div>

        <div class="mt-6 text-center">
            <a href="{{ route('home') }}" class="text-xs font-medium text-slate-400 hover:text-slate-600 transition">
                ← Retour à la boutique
            </a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('eye-icon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />';
            } else {
                pwd.type = 'password';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
            }
        }
    </script>
</body>
</html>
