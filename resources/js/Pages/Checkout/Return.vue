<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
  CheckCircle2,
  Clock,
  Package,
  RefreshCw,
  ArrowRight,
  XCircle,
  ShoppingBag,
} from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import { formatXof } from '@/Utils/format';

const props = defineProps<{
  order: {
    id: string;
    reference: string;
    product_name: string;
    quantity: number;
    total_xof: number;
    status: string;
    payment_url?: string;
  };
}>();

const isPending = props.order.status === 'pending_payment';
const isSuccess = ['paid', 'processing', 'activation_awaiting', 'delivered'].includes(props.order.status);
const isFailed = ['payment_failed', 'expired', 'cancelled'].includes(props.order.status);

let timer: any = null;
onMounted(() => {
  if (isPending) {
    timer = setInterval(() => {
      router.reload({ preserveScroll: true });
    }, 4000);
  }
});

onUnmounted(() => {
  if (timer) clearInterval(timer);
});
</script>

<template>
  <SiteLayout>
    <Head title="Suivi du paiement" />

    <div class="container-page py-12 max-w-lg">
      <div class="card p-6 shadow-pop sm:p-8 text-center space-y-6">
        <!-- Status Icon -->
        <div class="flex justify-center">
          <span
            v-if="isSuccess"
            class="flex size-16 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 shadow-sm"
          >
            <CheckCircle2 class="size-8" />
          </span>
          <span
            v-else-if="isPending"
            class="flex size-16 items-center justify-center rounded-2xl bg-sky-50 text-sky-600 shadow-sm animate-pulse"
          >
            <Clock class="size-8" />
          </span>
          <span
            v-else
            class="flex size-16 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 shadow-sm"
          >
            <XCircle class="size-8" />
          </span>
        </div>

        <!-- Titles & Text -->
        <div class="space-y-2">
          <h1 v-if="isSuccess" class="h2 text-2xl text-slate-900">
            Paiement reçu avec succès
          </h1>
          <h1 v-else-if="isPending" class="h2 text-2xl text-slate-900">
            Confirmation du paiement en attente
          </h1>
          <h1 v-else class="h2 text-2xl text-slate-900">
            Le paiement n'a pas abouti
          </h1>

          <p v-if="isSuccess" class="text-sm text-muted leading-relaxed">
            Nous préparons vos accès. Vous pouvez dès à présent suivre l'état de votre commande.
          </p>
          <p v-else-if="isPending" class="text-sm text-muted leading-relaxed">
            Nous attendons la confirmation de votre opérateur. Cette page se met à jour toute seule : inutile de payer à nouveau.
          </p>
          <p v-else class="text-sm text-muted leading-relaxed">
            Aucun montant n'a été prélevé sur votre compte. Vous pouvez vérifier votre solde et réessayer.
          </p>
        </div>

        <!-- Order Summary Card -->
        <div class="rounded-xl border border-line bg-canvas p-4 text-left text-xs space-y-2.5">
          <div class="flex items-center justify-between">
            <span class="text-muted">Référence :</span>
            <span class="font-mono font-bold text-ink">{{ order.reference }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-muted">Offre :</span>
            <span class="font-semibold text-ink">{{ order.product_name }} × {{ order.quantity }}</span>
          </div>
          <div class="flex items-center justify-between border-t border-line/60 pt-2 font-medium">
            <span class="text-muted">Montant :</span>
            <span class="font-bold text-brand">{{ formatXof(order.total_xof) }}</span>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-3 pt-2">
          <Link
            v-if="isSuccess || !isFailed"
            :href="`/compte/achats/${order.id}`"
            class="btn btn-primary btn-block min-h-12 text-sm shadow-glow"
          >
            <Package class="size-4" />
            Accéder à ma commande
            <ArrowRight class="size-4 ml-1" />
          </Link>

          <Link
            v-if="isFailed"
            href="/boutique"
            class="btn btn-primary btn-block min-h-12 text-sm shadow-glow"
          >
            <ShoppingBag class="size-4" />
            Retour à la boutique
          </Link>

          <Link
            href="/compte/achats"
            class="btn btn-soft btn-block text-xs"
          >
            Voir tous mes achats
          </Link>
        </div>
      </div>
    </div>
  </SiteLayout>
</template>
