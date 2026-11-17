<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { UserRound, Save, LogOut, ShieldAlert } from 'lucide-vue-next';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import CurrencySwitch from '@/Components/Shared/CurrencySwitch.vue';
import LanguageSwitch from '@/Components/Shared/LanguageSwitch.vue';
import { COUNTRIES } from '@/Utils/countries';

const props = defineProps<{
  user: {
    id: string;
    email: string;
    first_name?: string;
    last_name?: string;
    phone?: string;
    country: string;
    lang: string;
    mail_reminders: boolean;
  };
}>();

const identityForm = useForm({
  first_name: props.user.first_name || '',
  last_name: props.user.last_name || '',
  phone: props.user.phone || '',
  country: props.user.country || 'BF',
});

const reminderForm = useForm({
  mail_reminders: props.user.mail_reminders,
});

const submitIdentity = () => {
  identityForm.post('/compte/profil', {
    preserveScroll: true,
  });
};

const submitReminders = () => {
  reminderForm.post('/compte/profil', {
    preserveScroll: true,
  });
};

const logoutCurrent = () => {
  router.post('/deconnexion');
};

const logoutEverywhere = () => {
  router.post('/compte/profil/deconnecter-partout');
};
</script>

<template>
  <AccountLayout>
    <Head title="Profil" />

    <div class="space-y-6 max-w-2xl">
      <div>
        <h1 class="h2 text-2xl font-bold text-ink">Profil</h1>
      </div>

      <!-- Identité -->
      <section class="card p-5 space-y-4">
        <h2 class="h3 text-lg font-bold text-ink">Identité</h2>
        <div>
          <p class="text-xs text-muted">Adresse e-mail</p>
          <p class="text-sm font-semibold break-all text-ink mt-0.5">{{ user.email }}</p>
          <p class="hint">Connexion par code e-mail, sans mot de passe.</p>
        </div>

        <form @submit.prevent="submitIdentity" class="mt-4 space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label for="firstName" class="label">Prénom</label>
              <input
                id="firstName"
                v-model="identityForm.first_name"
                autocomplete="given-name"
                maxlength="100"
                class="input"
              />
            </div>
            <div>
              <label for="lastName" class="label">Nom</label>
              <input
                id="lastName"
                v-model="identityForm.last_name"
                autocomplete="family-name"
                maxlength="100"
                class="input"
              />
            </div>
            <div>
              <label for="phone" class="label">Téléphone Mobile Money</label>
              <input
                id="phone"
                v-model="identityForm.phone"
                type="tel"
                autocomplete="tel"
                maxlength="30"
                placeholder="Ex. 70 00 00 00"
                class="input"
              />
            </div>
            <div>
              <label for="country" class="label">Pays</label>
              <select
                id="country"
                v-model="identityForm.country"
                class="input"
              >
                <option value="">Non renseigné</option>
                <option v-for="c in COUNTRIES" :key="c.code" :value="c.code">
                  {{ c.flag }} {{ c.name }} ({{ c.dial }})
                </option>
              </select>
            </div>
          </div>

          <button
            type="submit"
            :disabled="identityForm.processing"
            class="btn btn-brand min-h-11 px-6 shadow-sm"
          >
            <span>{{ identityForm.processing ? 'Enregistrement...' : 'Enregistrer' }}</span>
          </button>
        </form>
      </section>

      <!-- Préférences -->
      <section class="card p-5 space-y-4">
        <h2 class="h3 text-lg font-bold text-ink">Préférences</h2>

        <div>
          <p class="text-sm font-semibold text-ink mb-2">Langue</p>
          <LanguageSwitch />
        </div>

        <div>
          <p class="text-sm font-semibold text-ink mb-2">Devise</p>
          <CurrencySwitch />
          <p class="hint">
            Les prix peuvent s'afficher en FCFA ou en dollars (taux de change réel). Le paiement se fait toujours en FCFA.
          </p>
        </div>

        <form @submit.prevent="submitReminders" class="space-y-3 border-t border-line pt-4">
          <label class="flex cursor-pointer items-start gap-3">
            <input
              type="checkbox"
              v-model="reminderForm.mail_reminders"
              class="mt-1 size-4 shrink-0 accent-brand"
            />
            <span class="text-sm">
              <span class="font-semibold text-ink">Rappels de paiement par e-mail</span>
              <span class="block text-xs leading-relaxed text-muted">
                Un e-mail (deux au maximum) si vous démarrez une commande sans la payer. Les e-mails de confirmation de commande restent toujours envoyés.
              </span>
            </span>
          </label>
          <button
            type="submit"
            :disabled="reminderForm.processing"
            class="btn btn-soft btn-sm"
          >
            Enregistrer
          </button>
        </form>
      </section>

      <!-- Session -->
      <section class="card p-5 space-y-4">
        <h2 class="h3 text-lg font-bold text-ink">Session</h2>
        <div class="flex flex-wrap gap-3">
          <button
            type="button"
            @click="logoutCurrent"
            class="btn btn-soft"
          >
            <LogOut class="size-4" />
            Se déconnecter
          </button>

          <button
            type="button"
            @click="logoutEverywhere"
            class="btn btn-danger"
          >
            <ShieldAlert class="size-4" />
            Déconnecter tous mes appareils
          </button>
        </div>
      </section>
    </div>
  </AccountLayout>
</template>
