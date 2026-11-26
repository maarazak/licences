<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
  Wallet,
  Plus,
  ArrowDownRight,
  ArrowUpRight,
  ShieldCheck,
  CheckCircle2,
  CreditCard,
  Zap,
} from 'lucide-vue-next';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import PaymentLogos from '@/Components/Shared/PaymentLogos.vue';
import PaymentOptionCard from '@/Components/Checkout/PaymentOptionCard.vue';
import { PAYMENT_COUNTRIES } from '@/Utils/countries';
import { CRYPTO_ICON_LOGOS, CRYPTO_LOGOS, MOBILE_ICON_LOGOS, MOBILE_LOGOS } from '@/Utils/paymentLogos';
import { useI18n } from '@/Utils/useI18n';

const props = defineProps<{
  defaultCountry?: string;
  entries: {
    data: any[];
    current_page: number;
    last_page: number;
  };
}>();

const page = usePage();
const authUser = page.props.auth?.user as any;
const { t, format, lang } = useI18n();

const showDepositModal = ref(false);
const quickAmounts = [2000, 5000, 10000, 25000, 50000];

const depositForm = useForm({
  amount_xof: 5000,
  country: props.defaultCountry || 'BF',
  phone: '',
  gateway: 'cinetpay',
});

const selectQuickAmount = (val: number) => {
  depositForm.amount_xof = val;
};

const submitDeposit = () => {
  depositForm.post('/compte/solde/recharger', {
    preserveScroll: true,
  });
};
</script>

<template>
  <AccountLayout>
    <Head :title="`${t('Mon solde')} — ${t('Portefeuille client')}`" />

    <div class="space-y-8">
      <!-- Balance Card -->
      <div class="card p-6 sm:p-8 bg-gradient-to-br from-white via-teal-50/30 to-teal-50 border-teal-200/60 shadow-card">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
          <div class="space-y-1">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">{{ t('Solde disponible') }}</span>
            <p class="font-display text-3xl sm:text-4xl font-extrabold text-[#0d5c4a]">
              {{ format(authUser?.balance_xof || 0) }}
            </p>
            <p class="text-xs text-slate-500">
              {{ t("Utilisable instantanément pour régler n'importe quelle offre sur la boutique.") }}
            </p>
          </div>

          <button
            type="button"
            @click="showDepositModal = !showDepositModal"
            class="btn btn-primary min-h-12 px-6 self-start sm:self-auto shadow-glow cursor-pointer"
          >
            <Plus class="size-4" />
            {{ t('Recharger mon solde') }}
          </button>
        </div>
      </div>

      <!-- Deposit Form / Modal -->
      <div v-if="showDepositModal" class="card p-6 sm:p-8 space-y-6 border-[#0d5c4a]/40 shadow-pop animate-fade-up">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <div class="space-y-1">
            <h2 class="h3 text-lg">{{ t('Recharger votre solde en FCFA') }}</h2>
            <p class="text-xs text-slate-500">
              {{ t('Paiement sécurisé par Orange Money, Moov, MTN ou Wave. Crédit immédiat.') }}
            </p>
          </div>
          <button
            type="button"
            @click="showDepositModal = false"
            class="text-xs font-bold text-slate-400 hover:text-slate-600 cursor-pointer"
          >
            {{ t('Fermer') }}
          </button>
        </div>

        <form @submit.prevent="submitDeposit" class="space-y-6">
          <!-- Quick Amount Buttons -->
          <div>
            <label class="label text-xs">{{ t('Montant à créditer (FCFA)') }}</label>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 mb-3">
              <button
                v-for="amt in quickAmounts"
                :key="amt"
                type="button"
                @click="selectQuickAmount(amt)"
                :class="[
                  'py-2 px-3 rounded-xl border text-xs font-bold transition cursor-pointer',
                  depositForm.amount_xof === amt
                    ? 'border-[#0d5c4a] bg-[#0d5c4a] text-white shadow-sm'
                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                ]"
              >
                {{ format(amt) }}
              </button>
            </div>
            <input
              v-model.number="depositForm.amount_xof"
              type="number"
              min="500"
              max="1000000"
              required
              class="input font-bold text-[#0d5c4a] text-base"
              :placeholder="t('Montant libre (min. 500 FCFA)')"
            />
          </div>

          <!-- Gateway selection -->
          <div>
            <label class="label text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 block">{{ t('Moyen de rechargement') }}</label>
            <div class="mt-1 space-y-3" role="radiogroup" :aria-label="t('Moyen de rechargement')">
              <PaymentOptionCard
                :selected="depositForm.gateway === 'cinetpay'"
                :icon-logos="MOBILE_ICON_LOGOS"
                :title="t('Mobile Money & Carte')"
                :logos="MOBILE_LOGOS"
                @select="depositForm.gateway = 'cinetpay'"
              />
              <PaymentOptionCard
                :selected="depositForm.gateway === 'nowpayments'"
                :icon-logos="CRYPTO_ICON_LOGOS"
                :title="t('Crypto-monnaies')"
                :logos="CRYPTO_LOGOS"
                @select="depositForm.gateway = 'nowpayments'"
              />
            </div>
          </div>

          <!-- Country selection (handled directly on CinetPay) -->
          <div v-if="depositForm.gateway === 'cinetpay'">
            <label class="label text-xs">{{ t('Pays de facturation') }}</label>
            <select
              v-model="depositForm.country"
              class="input cursor-pointer"
            >
              <option
                v-for="c in PAYMENT_COUNTRIES"
                :key="c.code"
                :value="c.code"
              >
                {{ c.flag }} {{ c.name }} ({{ c.dial }})
              </option>
            </select>
          </div>

          <div class="flex items-center justify-between pt-2">
            <PaymentLogos layout="row" />
            <button
              type="submit"
              :disabled="depositForm.processing"
              class="btn btn-primary min-h-12 px-6 cursor-pointer"
            >
              <span v-if="depositForm.processing">{{ t('Initialisation...') }}</span>
              <span v-else>{{ t('Payer {amount}', { amount: format(depositForm.amount_xof || 0) }) }}</span>
            </button>
          </div>
        </form>
      </div>

      <!-- Entries Table -->
      <div class="card p-6 shadow-card space-y-4">
        <h2 class="h3 text-base border-b border-slate-100 pb-3">{{ t('Historique des mouvements de solde') }}</h2>

        <div v-if="entries.data.length" class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="text-slate-400 border-b border-slate-100 uppercase tracking-wider font-semibold">
              <tr>
                <th class="pb-3">{{ t('Date') }}</th>
                <th class="pb-3">{{ t('Description') }}</th>
                <th class="pb-3">{{ t('Mouvement') }}</th>
                <th class="pb-3 text-right">{{ t('Solde après') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
              <tr v-for="entry in entries.data" :key="entry.id" class="hover:bg-slate-50/70 transition">
                <td class="py-3.5 text-slate-500">
                  {{ new Date(entry.created_at).toLocaleDateString(lang === 'en' ? 'en-US' : 'fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                </td>
                <td class="py-3.5 max-w-sm truncate">{{ entry.description }}</td>
                <td class="py-3.5">
                  <span
                    :class="[
                      'font-bold inline-flex items-center gap-1',
                      entry.amount_xof > 0 ? 'text-emerald-600' : 'text-slate-900',
                    ]"
                  >
                    <ArrowDownRight v-if="entry.amount_xof > 0" class="size-3.5" />
                    <ArrowUpRight v-else class="size-3.5 text-slate-400" />
                    {{ entry.amount_xof > 0 ? '+' : '' }}{{ format(entry.amount_xof) }}
                  </span>
                </td>
                <td class="py-3.5 text-right font-bold text-slate-800">
                  {{ format(entry.balance_after_xof || 0) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="text-center py-10 text-xs text-slate-400">
          {{ t('Aucun mouvement de solde pour le moment.') }}
        </div>
      </div>
    </div>
  </AccountLayout>
</template>
