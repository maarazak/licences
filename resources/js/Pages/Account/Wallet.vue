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
} from 'lucide-vue-next';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import PaymentLogos from '@/Components/Shared/PaymentLogos.vue';
import { COUNTRIES } from '@/Utils/countries';

const props = defineProps<{
  entries: {
    data: any[];
    current_page: number;
    last_page: number;
  };
}>();

const page = usePage();
const authUser = page.props.auth?.user as any;

const showDepositModal = ref(false);
const quickAmounts = [2000, 5000, 10000, 25000, 50000];

const depositForm = useForm({
  amount_xof: 5000,
  country: authUser?.country || 'BF',
  phone: '',
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
    <Head title="Mon solde — Portefeuille client" />

    <div class="space-y-8">
      <!-- Balance Card -->
      <div class="card p-6 sm:p-8 bg-gradient-to-br from-white via-teal-50/30 to-teal-50 border-teal-200/60 shadow-card">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
          <div class="space-y-1">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Solde disponible</span>
            <p class="font-display text-3xl sm:text-4xl font-extrabold text-[#0d5c4a]">
              {{ (authUser?.balance_xof || 0).toLocaleString('fr-FR') }} FCFA
            </p>
            <p class="text-xs text-slate-500">
              Utilisable instantanément pour régler n'importe quelle offre sur la boutique.
            </p>
          </div>

          <button
            type="button"
            @click="showDepositModal = !showDepositModal"
            class="btn btn-primary min-h-12 px-6 self-start sm:self-auto shadow-glow"
          >
            <Plus class="size-4" />
            Recharger mon solde
          </button>
        </div>
      </div>

      <!-- Deposit Form / Modal -->
      <div v-if="showDepositModal" class="card p-6 sm:p-8 space-y-6 border-[#0d5c4a]/40 shadow-pop animate-fade-up">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <div class="space-y-1">
            <h2 class="h3 text-lg">Recharger votre solde en FCFA</h2>
            <p class="text-xs text-slate-500">
              Paiement sécurisé par Orange Money, Moov, MTN ou Wave. Crédit immédiat.
            </p>
          </div>
          <button
            type="button"
            @click="showDepositModal = false"
            class="text-xs font-bold text-slate-400 hover:text-slate-600"
          >
            Fermer
          </button>
        </div>

        <form @submit.prevent="submitDeposit" class="space-y-6">
          <!-- Quick Amount Buttons -->
          <div>
            <label class="label text-xs">Montant à créditer (FCFA)</label>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 mb-3">
              <button
                v-for="amt in quickAmounts"
                :key="amt"
                type="button"
                @click="selectQuickAmount(amt)"
                :class="[
                  'py-2 px-3 rounded-xl border text-xs font-bold transition',
                  depositForm.amount_xof === amt
                    ? 'border-[#0d5c4a] bg-[#0d5c4a] text-white shadow-sm'
                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                ]"
              >
                {{ amt.toLocaleString('fr-FR') }} F
              </button>
            </div>
            <input
              v-model.number="depositForm.amount_xof"
              type="number"
              min="500"
              max="1000000"
              required
              class="input font-bold text-[#0d5c4a] text-base"
              placeholder="Montant libre (min. 500 FCFA)"
            />
          </div>

          <!-- Country selection (handled directly on CinetPay) -->
          <div>
            <label class="label text-xs">Pays de facturation</label>
            <select
              v-model="depositForm.country"
              class="input cursor-pointer"
            >
              <option
                v-for="c in COUNTRIES"
                :key="c.code"
                :value="c.code"
              >
                {{ c.flag }} {{ c.name }} ({{ c.dial }})
              </option>
            </select>
            <p class="mt-1.5 text-xs text-slate-500">
              Le choix du moyen de paiement (Carte Bancaire Visa/Mastercard ou Mobile Money : Orange Money, Moov, MTN, Wave...) s'effectue directement sur la page sécurisée CinetPay.
            </p>
          </div>

          <div class="flex items-center justify-between pt-2">
            <PaymentLogos layout="row" />
            <button
              type="submit"
              :disabled="depositForm.processing"
              class="btn btn-primary min-h-12 px-6"
            >
              <span v-if="depositForm.processing">Initialisation...</span>
              <span v-else>Payer {{ (depositForm.amount_xof || 0).toLocaleString('fr-FR') }} FCFA</span>
            </button>
          </div>
        </form>
      </div>

      <!-- Entries Table -->
      <div class="card p-6 shadow-card space-y-4">
        <h2 class="h3 text-base border-b border-slate-100 pb-3">Historique des mouvements de solde</h2>

        <div v-if="entries.data.length" class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="text-slate-400 border-b border-slate-100 uppercase tracking-wider font-semibold">
              <tr>
                <th class="pb-3">Date</th>
                <th class="pb-3">Description</th>
                <th class="pb-3">Mouvement</th>
                <th class="pb-3 text-right">Solde après</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
              <tr v-for="entry in entries.data" :key="entry.id" class="hover:bg-slate-50/70 transition">
                <td class="py-3.5 text-slate-500">
                  {{ new Date(entry.created_at).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
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
                    {{ entry.amount_xof > 0 ? '+' : '' }}{{ entry.amount_xof.toLocaleString('fr-FR') }} FCFA
                  </span>
                </td>
                <td class="py-3.5 text-right font-bold text-slate-800">
                  {{ (entry.balance_after_xof || 0).toLocaleString('fr-FR') }} FCFA
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="text-center py-10 text-xs text-slate-400">
          Aucun mouvement de solde pour le moment.
        </div>
      </div>
    </div>
  </AccountLayout>
</template>
