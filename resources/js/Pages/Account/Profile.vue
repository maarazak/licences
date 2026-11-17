<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { UserRound, Save, CheckCircle2 } from 'lucide-vue-next';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import { COUNTRIES } from '@/Utils/countries';

const props = defineProps<{
  user: {
    id: string;
    email: string;
    phone?: string;
    country: string;
    lang: string;
  };
}>();

const form = useForm({
  phone: props.user.phone || '',
  country: props.user.country || 'BF',
  lang: props.user.lang || 'fr',
});

const submitProfile = () => {
  form.post('/compte/profil', {
    preserveScroll: true,
  });
};
</script>

<template>
  <AccountLayout>
    <Head title="Profil & Préférences — Espace client" />

    <div class="space-y-6 max-w-2xl">
      <div>
        <h1 class="h1 text-2xl">Profil & Préférences</h1>
        <p class="text-xs text-slate-500 mt-1">
          Personnalisez votre pays par défaut et vos coordonnées Mobile Money.
        </p>
      </div>

      <div class="card p-6 sm:p-8 shadow-card space-y-6">
        <!-- Static Email info -->
        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-1">
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Identifiant de compte</span>
          <p class="font-bold text-slate-900 text-sm">{{ user.email }}</p>
          <p class="text-[11px] text-slate-500">
            Cet e-mail est utilisé pour vos codes de connexion et vos livraisons de licences.
          </p>
        </div>

        <form @submit.prevent="submitProfile" class="space-y-5">
          <div>
            <label class="label text-xs">Pays de facturation et Mobile Money</label>
            <select
              v-model="form.country"
              class="input font-medium"
            >
              <option
                v-for="c in COUNTRIES"
                :key="c.code"
                :value="c.code"
              >
                {{ c.flag }} {{ c.name }} ({{ c.dial }})
              </option>
            </select>
            <p class="hint">Ce pays sera présélectionné lors de vos prochains paiements.</p>
          </div>

          <div>
            <label class="label text-xs">Numéro de téléphone Mobile Money</label>
            <input
              v-model="form.phone"
              type="tel"
              placeholder="Ex. 70 00 00 00"
              class="input font-mono"
            />
          </div>

          <div>
            <label class="label text-xs">Langue d'affichage</label>
            <select
              v-model="form.lang"
              class="input font-medium"
            >
              <option value="fr">Français</option>
              <option value="en">English</option>
            </select>
          </div>

          <div class="pt-4 border-t border-slate-100 flex justify-end">
            <button
              type="submit"
              :disabled="form.processing"
              class="btn btn-primary btn-sm min-h-11 px-6 shadow-sm"
            >
              <Save class="size-4" />
              <span>{{ form.processing ? 'Enregistrement...' : 'Enregistrer mes réglages' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AccountLayout>
</template>
