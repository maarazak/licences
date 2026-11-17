<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
  CheckCircle2,
  Clock,
  AlertCircle,
  Package,
  RefreshCw,
  ArrowRight,
} from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';

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

const isPaid = props.order.status === 'paid' || props.order.status === 'delivered';
const isPending = props.order.status === 'pending_payment';
const isFailed = props.order.status === 'payment_failed' || props.order.status === 'expired';
</script>

<template>
  <SiteLayout>
    <Head title="Confirmation de paiement" />

    <div class="container-page py-16 max-w-xl">
      <div class="card p-8 text-center space-y-6 shadow-pop">
        <!-- Status Icon -->
        <div class="mx-auto">
          <div
            v-if="isPaid"
            class="size-20 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto"
          >
            <CheckCircle2 class="size-10" />
          </div>
          <div
            v-else-if="isPending"
            class="size-20 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center mx-auto"
          >
            <Clock class="size-10 animate-pulse" />
          </div>
          <div
            v-else
            class="size-20 rounded-full bg-red-50 text-red-600 flex items-center justify-center mx-auto"
          >
            <AlertCircle class="size-10" />
          </div>
        </div>

        <!-- Status Titles -->
        <div class="space-y-2">
          <h1 v-if="isPaid" class="h2 text-2xl text-emerald-800">Paiement validé avec succès !</h1>
          <h1 v-else-if="isPending" class="h2 text-2xl text-amber-800">Paiement en cours de traitement</h1>
          <h1 v-else class="h2 text-2xl text-red-800">Le paiement n'a pas abouti</h1>

          <p v-if="isPaid" class="text-sm text-slate-600 leading-relaxed">
            Votre commande a bien été enregistrée. Votre accès numérique est disponible dans votre espace client.
          </p>
          <p v-else-if="isPending" class="text-sm text-slate-600 leading-relaxed">
            Nous attendons la confirmation finale de votre opérateur Mobile Money. La page se mettra à jour automatiquement.
          </p>
          <p v-else class="text-sm text-slate-600 leading-relaxed">
            Aucun montant n'a été prélevé sur votre compte. Vous pouvez relancer le paiement dès maintenant.
          </p>
        </div>

        <!-- Order Summary Box -->
        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 text-left text-xs space-y-2.5">
          <div class="flex justify-between">
            <span class="text-slate-500">Référence :</span>
            <span class="font-mono font-bold text-slate-900">{{ order.reference }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Offre :</span>
            <span class="font-semibold text-slate-900">{{ order.product_name }} × {{ order.quantity }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Montant :</span>
            <span class="font-bold text-[#0d5c4a]">{{ (order.total_xof || 0).toLocaleString('fr-FR') }} FCFA</span>
          </div>
        </div>

        <!-- Action Links -->
        <div class="space-y-3 pt-2">
          <Link
            :href="`/compte/achats/${order.id}`"
            class="btn btn-primary btn-block min-h-12"
          >
            <Package class="size-4" />
            Accéder à ma commande et mes accès
          </Link>

          <a
            v-if="isPending && order.id"
            :href="`/paiement/reprendre?commande=${order.id}`"
            class="btn btn-soft btn-block min-h-11"
          >
            <RefreshCw class="size-4" />
            Reprendre la session de paiement
          </a>

          <Link
            href="/boutique"
            class="inline-block text-xs font-semibold text-slate-500 hover:text-slate-900 transition pt-2"
          >
            Retourner à la boutique
          </Link>
        </div>
      </div>
    </div>
  </SiteLayout>
</template>
