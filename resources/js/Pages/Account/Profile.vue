<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { UserRound, Save, LogOut, ShieldAlert } from 'lucide-vue-next';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import CurrencySwitch from '@/Components/Shared/CurrencySwitch.vue';
import LanguageSwitch from '@/Components/Shared/LanguageSwitch.vue';
import { COUNTRIES } from '@/Utils/countries';
import { useI18n } from '@/Utils/useI18n';

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

const { t } = useI18n();

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
    <Head :title="`${t('Profil')} — ${t('Espace client')}`" />

    <div class="space-y-6 max-w-2xl">
      <div>
        <h1 class="h2 text-2xl font-bold text-ink">{{ t('Profil') }}</h1>
      </div>

      <!-- Identité -->
      <section class="card p-5 space-y-4">
        <h2 class="h3 text-lg font-bold text-ink">{{ t('Identité') }}</h2>
        <div>
          <p class="text-xs text-muted">{{ t('Adresse e-mail') }}</p>
          <p class="text-sm font-semibold break-all text-ink mt-0.5">{{ user.email }}</p>
          <p class="hint">{{ t('Connexion par code e-mail, sans mot de passe.') }}</p>
        </div>

        <form @submit.prevent="submitIdentity" class="mt-4 space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label for="firstName" class="label">{{ t('Prénom') }}</label>
              <input
                id="firstName"
                v-model="identityForm.first_name"
                autocomplete="given-name"
                maxlength="100"
                class="input"
              />
            </div>
            <div>
              <label for="lastName" class="label">{{ t('Nom') }}</label>
              <input
                id="lastName"
                v-model="identityForm.last_name"
                autocomplete="family-name"
                maxlength="100"
                class="input"
              />
            </div>
            <div>
              <label for="phone" class="label">{{ t('Téléphone Mobile Money') }}</label>
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
              <label for="country" class="label">{{ t('Pays') }}</label>
              <select
                id="country"
                v-model="identityForm.country"
                class="input cursor-pointer"
              >
                <option value="">{{ t('Non renseigné') }}</option>
                <option v-for="c in COUNTRIES" :key="c.code" :value="c.code">
                  {{ c.flag }} {{ c.name }} ({{ c.dial }})
                </option>
              </select>
            </div>
          </div>

          <button
            type="submit"
            :disabled="identityForm.processing"
            class="btn btn-brand min-h-11 px-6 shadow-sm cursor-pointer"
          >
            <span>{{ identityForm.processing ? t('Enregistrement...') : t('Enregistrer') }}</span>
          </button>
        </form>
      </section>

      <!-- Préférences -->
      <section class="card p-5 space-y-4">
        <h2 class="h3 text-lg font-bold text-ink">{{ t('Préférences') }}</h2>

        <div>
          <p class="text-sm font-semibold text-ink mb-2">{{ t('Langue') }}</p>
          <LanguageSwitch />
        </div>

        <div>
          <p class="text-sm font-semibold text-ink mb-2">{{ t('Devise') }}</p>
          <CurrencySwitch />
          <p class="hint">
            {{ t("Les prix peuvent s'afficher en FCFA ou en dollars (taux de change réel). Le paiement se fait toujours en FCFA.") }}
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
              <span class="font-semibold text-ink">{{ t('Rappels de paiement par e-mail') }}</span>
              <span class="block text-xs leading-relaxed text-muted">
                {{ t('Un e-mail (deux au maximum) si vous démarrez une commande sans la payer. Les e-mails de confirmation de commande restent toujours envoyés.') }}
              </span>
            </span>
          </label>
          <button
            type="submit"
            :disabled="reminderForm.processing"
            class="btn btn-soft btn-sm cursor-pointer"
          >
            {{ t('Enregistrer') }}
          </button>
        </form>
      </section>

      <!-- Session -->
      <section class="card p-5 space-y-4">
        <h2 class="h3 text-lg font-bold text-ink">{{ t('Session') }}</h2>
        <div class="flex flex-wrap gap-3">
          <button
            type="button"
            @click="logoutCurrent"
            class="btn btn-soft cursor-pointer"
          >
            <LogOut class="size-4" />
            {{ t('Se déconnecter') }}
          </button>

          <button
            type="button"
            @click="logoutEverywhere"
            class="btn btn-danger cursor-pointer"
          >
            <ShieldAlert class="size-4" />
            {{ t('Déconnecter tous mes appareils') }}
          </button>
        </div>
      </section>
    </div>
  </AccountLayout>
</template>
