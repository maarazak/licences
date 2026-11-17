<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
  Mail,
  ArrowRight,
  ShieldCheck,
  KeyRound,
  CheckCircle2,
} from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';

const props = defineProps<{
  next?: string;
}>();

const page = usePage();
const step = ref<'email' | 'code'>((page.props.flash as any)?.step || 'email');
const emailHolder = ref<string>((page.props.flash as any)?.email || '');

const requestForm = useForm({
  email: '',
});

const verifyForm = useForm({
  email: '',
  code: '',
  next: props.next || '/compte/achats',
});

const submitEmail = () => {
  requestForm.post('/connexion/demander-code', {
    onSuccess: (pageRes: any) => {
      emailHolder.value = requestForm.email;
      verifyForm.email = requestForm.email;
      step.value = 'code';
    },
  });
};

const submitCode = () => {
  verifyForm.post('/connexion/verifier-code', {
    preserveScroll: true,
  });
};

const changeEmail = () => {
  step.value = 'email';
  verifyForm.code = '';
};
</script>

<template>
  <SiteLayout>
    <Head title="Connexion à votre espace client" />

    <div class="container-page py-16 max-w-md">
      <div class="card p-8 space-y-6 shadow-pop">
        <!-- Header -->
        <div class="text-center space-y-2">
          <div class="size-14 rounded-2xl bg-[#0d5c4a]/10 text-[#0d5c4a] flex items-center justify-center mx-auto mb-3">
            <KeyRound class="size-7" />
          </div>
          <h1 class="h2 text-2xl">Espace client</h1>
          <p class="text-xs text-slate-500 leading-relaxed">
            Accédez à vos commandes, licences livrées et à votre solde en toute sécurité.
          </p>
        </div>

        <!-- Step 1 : Request Code by Email -->
        <form
          v-if="step === 'email'"
          @submit.prevent="submitEmail"
          class="space-y-4"
        >
          <div>
            <label class="label text-xs">Votre adresse e-mail</label>
            <div class="relative">
              <Mail class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
              <input
                v-model="requestForm.email"
                type="email"
                required
                autofocus
                placeholder="votre.email@gmail.com"
                class="input pl-10"
              />
            </div>
            <p class="hint">Aucun mot de passe à retenir : un code de connexion à 6 chiffres vous sera envoyé.</p>
          </div>

          <button
            type="submit"
            :disabled="requestForm.processing"
            class="btn btn-primary btn-block min-h-12 shadow-glow"
          >
            <span v-if="requestForm.processing">Envoi du code...</span>
            <span v-else class="flex items-center justify-center gap-2">
              Recevoir mon code de connexion
              <ArrowRight class="size-4" />
            </span>
          </button>
        </form>

        <!-- Step 2 : Verify 6-digit Code -->
        <form
          v-else
          @submit.prevent="submitCode"
          class="space-y-4"
        >
          <div class="p-3.5 bg-teal-50 border border-teal-200/80 rounded-2xl text-xs text-slate-700 space-y-1">
            <p class="font-bold text-[#0d5c4a] flex items-center gap-1.5">
              <CheckCircle2 class="size-4 shrink-0" />
              Code envoyé !
            </p>
            <p>
              Un code à 6 chiffres a été expédié à <strong>{{ emailHolder }}</strong>. Vérifiez également vos courriers indésirables.
            </p>
          </div>

          <div>
            <label class="label text-xs text-center block">Saisissez le code à 6 chiffres</label>
            <input
              v-model="verifyForm.code"
              type="text"
              inputmode="numeric"
              pattern="[0-9]{6}"
              maxlength="6"
              required
              autofocus
              placeholder="123456"
              class="input text-center text-2xl font-mono tracking-[0.4em] font-bold"
            />
          </div>

          <button
            type="submit"
            :disabled="verifyForm.processing || verifyForm.code.length !== 6"
            class="btn btn-primary btn-block min-h-12 shadow-glow"
          >
            <span v-if="verifyForm.processing">Vérification...</span>
            <span v-else class="flex items-center justify-center gap-2">
              Valider et se connecter
              <ArrowRight class="size-4" />
            </span>
          </button>

          <button
            type="button"
            @click="changeEmail"
            class="btn btn-soft btn-block min-h-10 text-xs text-slate-600"
          >
            Utiliser une autre adresse e-mail
          </button>
        </form>

        <!-- Security footer badge -->
        <div class="pt-4 border-t border-slate-100 flex items-center justify-center gap-2 text-xs text-slate-400">
          <ShieldCheck class="size-4 text-[#0d5c4a]" />
          <span>Authentification sécurisée sans mot de passe</span>
        </div>
      </div>
    </div>
  </SiteLayout>
</template>
