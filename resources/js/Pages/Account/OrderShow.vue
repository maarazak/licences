<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
  ArrowLeft,
  KeyRound,
  Copy,
  Check,
  CheckCircle2,
  Clock,
  AlertCircle,
  LifeBuoy,
  RefreshCw,
  ShieldCheck,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import { ORDER_STATUS_LABELS } from '@/Types/enums';

const props = defineProps<{
  order: {
    id: string;
    reference: string;
    product_name: string;
    quantity: number;
    total_xof: number;
    status: string;
    paid_with: string;
    customer_phone?: string;
    customer_country?: string;
    delivery_content?: string | null;
    delivered_at?: string | null;
    paid_at?: string | null;
    created_at: string;
    payment_url?: string;
    events: Array<{
      id: string;
      event: string;
      message: string;
      created_at: string;
    }>;
  };
}>();

const copied = ref(false);

const copyCredentials = () => {
  if (props.order.delivery_content) {
    navigator.clipboard.writeText(props.order.delivery_content);
    copied.value = true;
    setTimeout(() => {
      copied.value = false;
    }, 2500);

    Swal.fire({
      icon: 'success',
      title: 'Accès copié dans le presse-papiers !',
      timer: 2500,
      showConfirmButton: false,
      toast: true,
      position: 'top-end',
    });
  }
};
</script>

<template>
  <AccountLayout>
    <Head :title="`Commande ${order.reference} — Mes achats`" />

    <div class="space-y-6 max-w-4xl">
      <!-- Breadcrumb -->
      <div class="flex items-center justify-between">
        <Link
          href="/compte/achats"
          class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-slate-900 transition"
        >
          <ArrowLeft class="size-4" />
          Retour à la liste des achats
        </Link>

        <span
          :class="[
            'badge text-xs px-3 py-1 font-bold',
            order.status === 'paid' || order.status === 'delivered' ? 'badge-success' : '',
            order.status === 'pending_payment' ? 'badge-warning' : '',
            order.status === 'failed' || order.status === 'payment_failed' ? 'badge-danger' : '',
          ]"
        >
          {{ ORDER_STATUS_LABELS[order.status as keyof typeof ORDER_STATUS_LABELS] || order.status }}
        </span>
      </div>

      <!-- Main Header Card -->
      <div class="card p-6 sm:p-8 space-y-4 shadow-card">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-5">
          <div>
            <span class="text-xs font-mono font-bold text-slate-400 block uppercase">Commande {{ order.reference }}</span>
            <h1 class="h2 text-2xl mt-0.5">{{ order.product_name }}</h1>
            <p class="text-xs text-slate-500 mt-1">
              Commandé le {{ new Date(order.created_at).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
            </p>
          </div>

          <div class="text-left sm:text-right">
            <span class="text-xs text-slate-400 block uppercase font-bold">Montant réglé</span>
            <p class="font-display text-2xl font-extrabold text-[#0d5c4a] mt-0.5">
              {{ (order.total_xof || 0).toLocaleString('fr-FR') }} FCFA
            </p>
            <span class="text-[11px] text-slate-500 block">
              Via {{ order.paid_with === 'balance' ? 'Solde du compte' : 'Mobile Money' }}
            </span>
          </div>
        </div>

        <!-- DELIVERED CREDENTIALS PANEL -->
        <div v-if="order.delivery_content" class="p-6 bg-slate-900 text-white rounded-2xl space-y-4 shadow-lg border border-slate-800">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5">
              <KeyRound class="size-5 text-[#19c39a]" />
              <h2 class="font-bold text-sm text-white">Vos accès livrés</h2>
            </div>

            <button
              type="button"
              @click="copyCredentials"
              class="btn btn-sm bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 text-xs inline-flex items-center gap-1.5"
            >
              <Check v-if="copied" class="size-3.5 text-emerald-400" />
              <Copy v-else class="size-3.5" />
              <span>{{ copied ? 'Copié !' : 'Copier l\'accès' }}</span>
            </button>
          </div>

          <div class="p-4 bg-slate-950/80 rounded-xl border border-slate-800 font-mono text-sm leading-relaxed text-teal-300 whitespace-pre-wrap select-all">
            {{ order.delivery_content }}
          </div>

          <p class="text-xs text-slate-400 leading-relaxed">
            Conservez ces identifiants précieusement. Ils restent enregistrés et accessibles à tout moment dans votre espace.
          </p>
        </div>

        <!-- PENDING PAYMENT RESUME -->
        <div v-else-if="order.status === 'pending_payment'" class="p-5 bg-amber-50 border border-amber-200/80 rounded-2xl space-y-3 text-amber-900">
          <div class="flex items-center gap-2 font-bold text-sm">
            <Clock class="size-4 shrink-0" />
            <span>Paiement en attente</span>
          </div>
          <p class="text-xs leading-relaxed text-amber-800">
            Votre session de paiement n'a pas encore été finalisée par Mobile Money. Vous pouvez reprendre le paiement immédiatement :
          </p>
          <a
            :href="`/paiement/reprendre?commande=${order.id}`"
            class="btn btn-primary btn-sm inline-flex items-center gap-2"
          >
            <RefreshCw class="size-3.5" />
            Reprendre le paiement en ligne
          </a>
        </div>

        <!-- PROCESSING / FAILED -->
        <div v-else-if="order.status === 'paid' || order.status === 'processing'" class="p-5 bg-teal-50 border border-teal-200/80 rounded-2xl text-xs text-teal-900 space-y-2">
          <p class="font-bold flex items-center gap-2">
            <Clock class="size-4 text-[#0d5c4a]" />
            Votre commande est payée et en cours d'activation
          </p>
          <p class="leading-relaxed">
            Notre système est en train de préparer votre accès numérique. Vous recevrez une notification par e-mail dès qu'il sera prêt.
          </p>
        </div>
      </div>

      <!-- Events Timeline & Help Link -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Events Journal -->
        <div class="md:col-span-2 card p-6 space-y-4 shadow-card">
          <h2 class="h3 text-sm font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-3">
            Journal de traitement
          </h2>

          <div class="space-y-3">
            <div
              v-for="ev in order.events"
              :key="ev.id"
              class="flex items-start gap-3 text-xs border-l-2 border-[#0d5c4a] pl-3 py-1"
            >
              <div>
                <p class="font-semibold text-slate-800">{{ ev.message }}</p>
                <p class="text-slate-400 mt-0.5">
                  {{ new Date(ev.created_at).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Need Help Box -->
        <div class="card p-6 space-y-4 shadow-card h-fit">
          <h2 class="h3 text-sm font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-3">
            Assistance dédiée
          </h2>

          <p class="text-xs text-slate-500 leading-relaxed">
            Un souci avec cet abonnement ou une question sur son activation ? Notre équipe vous répond rapidement.
          </p>

          <Link
            :href="`/compte/assistance/nouveau?commande=${order.id}`"
            class="btn btn-soft btn-block btn-sm text-xs justify-center"
          >
            <LifeBuoy class="size-4" />
            Ouvrir un ticket d'aide
          </Link>
        </div>
      </div>
    </div>
  </AccountLayout>
</template>
