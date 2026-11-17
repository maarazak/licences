<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
  ArrowLeft,
  ArrowRight,
  KeyRound,
  Mail,
  ShieldCheck,
  Zap,
} from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import { SITE_NAME } from '@/Utils/site';

const props = defineProps<{
  next?: string;
}>();

const page = usePage();
const flash = page.props.flash as any;
const step = ref<'email' | 'code'>(flash?.step === 'code' ? 'code' : 'email');
const emailHolder = ref<string>(flash?.email || '');
const generalError = ref<string>((page.props.errors as any)?.email || flash?.error || '');

const requestForm = useForm({
  email: flash?.email || '',
});

const verifyForm = useForm({
  email: flash?.email || '',
  code: '',
  next: props.next || '/compte/achats',
});

const points = [
  { icon: KeyRound, text: 'Aucun mot de passe à retenir' },
  { icon: Zap, text: 'Connexion en 30 secondes' },
  { icon: ShieldCheck, text: 'Vos accès réservés à votre compte' },
];

const submitEmail = () => {
  generalError.value = '';
  requestForm.post('/connexion/demander-code', {
    preserveScroll: true,
    onSuccess: (pageRes: any) => {
      emailHolder.value = requestForm.email;
      verifyForm.email = requestForm.email;
      step.value = 'code';
    },
    onError: (err) => {
      if (err.email) generalError.value = err.email;
    }
  });
};

const submitCode = () => {
  generalError.value = '';
  verifyForm.post('/connexion/verifier-code', {
    preserveScroll: true,
    onError: (err) => {
      if (err.code) generalError.value = err.code;
    }
  });
};

const handleCodeInput = (e: Event) => {
  const target = e.target as HTMLInputElement;
  target.value = target.value.replace(/\D/g, '').slice(0, 6);
  verifyForm.code = target.value;
};

const useOtherEmail = () => {
  step.value = 'email';
  verifyForm.code = '';
  generalError.value = '';
};
</script>

<template>
  <SiteLayout>
    <Head title="Connexion" />

    <div class="bg-hero relative overflow-hidden">
      <div aria-hidden="true" class="grid-lines absolute inset-0" />
      <div class="container-page relative flex justify-center py-12 sm:py-16">
        <div class="w-full max-w-md">
          <Link
            href="/boutique"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-muted hover:text-ink transition"
          >
            <ArrowLeft class="size-4" aria-hidden="true" />
            Retour à la boutique
          </Link>

          <div class="card mt-4 p-6 shadow-pop sm:p-8">
            <h1 class="h2">Connectez-vous à {{ SITE_NAME }}</h1>
            <p class="mt-2 mb-6 text-sm leading-relaxed text-muted">
              Saisissez votre e-mail : nous vous envoyons un code. Votre compte est créé automatiquement à la première connexion.
            </p>

            <!-- Étape 2 : Code reçu par email -->
            <form
              v-if="step === 'code'"
              @submit.prevent="submitCode"
              class="space-y-4"
            >
              <input type="hidden" name="email" :value="emailHolder" />
              <input type="hidden" name="next" :value="props.next || '/compte/achats'" />

              <p class="alert alert-info flex gap-3 text-xs leading-relaxed">
                <Mail class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <span>
                  Un code à 6 chiffres a été envoyé à <strong class="break-all">{{ emailHolder }}</strong>. Pensez à vérifier vos courriers indésirables.
                </span>
              </p>

              <div>
                <label for="code" class="label">Code reçu par e-mail</label>
                <input
                  id="code"
                  :value="verifyForm.code"
                  @input="handleCodeInput"
                  inputmode="numeric"
                  autocomplete="one-time-code"
                  pattern="[0-9]{6}"
                  maxlength="6"
                  required
                  autofocus
                  placeholder="000000"
                  class="input text-center font-display text-xl tracking-[0.4em]"
                />
              </div>

              <div v-if="verifyForm.errors.code || generalError" class="alert alert-error text-xs">
                {{ verifyForm.errors.code || generalError }}
              </div>

              <button
                type="submit"
                :disabled="verifyForm.processing || verifyForm.code.length !== 6"
                class="btn btn-primary btn-block min-h-12 shadow-glow"
              >
                <span v-if="verifyForm.processing">Vérification...</span>
                <span v-else class="flex items-center justify-center gap-2">
                  Se connecter
                  <ArrowRight class="size-4" aria-hidden="true" />
                </span>
              </button>

              <button
                type="button"
                @click="useOtherEmail"
                class="btn btn-soft btn-block"
              >
                Utiliser une autre adresse
              </button>
            </form>

            <!-- Étape 1 : Demande d'e-mail -->
            <form
              v-else
              @submit.prevent="submitEmail"
              class="space-y-4"
            >
              <div>
                <label for="email" class="label">Adresse e-mail</label>
                <input
                  id="email"
                  v-model="requestForm.email"
                  type="email"
                  autocomplete="email"
                  required
                  autofocus
                  maxlength="191"
                  placeholder="votre.email@exemple.com"
                  class="input"
                />
                <p class="hint">Pas de mot de passe. Le code est valable 10 minutes.</p>
              </div>

              <div v-if="requestForm.errors.email || generalError" class="alert alert-error text-xs">
                {{ requestForm.errors.email || generalError }}
              </div>

              <button
                type="submit"
                :disabled="requestForm.processing"
                class="btn btn-primary btn-block min-h-12 shadow-glow"
              >
                <span v-if="requestForm.processing">Envoi du code...</span>
                <span v-else class="flex items-center justify-center gap-2">
                  Recevoir un code
                  <ArrowRight class="size-4" aria-hidden="true" />
                </span>
              </button>
            </form>
          </div>

          <!-- Points rassurants sous la carte -->
          <ul class="mt-6 space-y-2.5">
            <li
              v-for="({ icon, text }) in points"
              :key="text"
              class="flex items-center gap-3 text-sm font-medium text-ink/75"
            >
              <component :is="icon" class="size-4 text-brand" aria-hidden="true" />
              {{ text }}
            </li>
          </ul>
        </div>
      </div>
    </div>
  </SiteLayout>
</template>
