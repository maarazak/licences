<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Package, ArrowRight, ExternalLink } from 'lucide-vue-next';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import { ORDER_STATUS_LABELS } from '@/Types/enums';
import { useI18n } from '@/Utils/useI18n';

const props = defineProps<{
  orders: {
    data: any[];
    current_page: number;
    last_page: number;
    links: any[];
  };
}>();

const { t, format, lang } = useI18n();
</script>

<template>
  <AccountLayout>
    <Head :title="`${t('Mes achats')} — ${t('Espace client')}`" />

    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="h1 text-2xl">{{ t('Mes achats') }}</h1>
          <p class="text-xs text-slate-500 mt-1">
            {{ t("Retrouvez l'historique complet de vos commandes et vos accès numériques livrés.") }}
          </p>
        </div>

        <Link href="/boutique" class="btn btn-primary btn-sm hidden sm:inline-flex">
          {{ t('Nouvel achat') }}
        </Link>
      </div>

      <div class="card p-6 shadow-card overflow-hidden">
        <div v-if="orders.data.length" class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="text-slate-400 border-b border-slate-100 uppercase tracking-wider font-semibold">
              <tr>
                <th class="pb-3.5">{{ t('Date') }}</th>
                <th class="pb-3.5">{{ t('Référence') }}</th>
                <th class="pb-3.5">{{ t('Offre & Quantité') }}</th>
                <th class="pb-3.5">{{ t('Montant') }}</th>
                <th class="pb-3.5">{{ t('Statut') }}</th>
                <th class="pb-3.5 text-right">{{ t('Détails') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
              <tr v-for="order in orders.data" :key="order.id" class="hover:bg-slate-50/70 transition">
                <td class="py-4 text-slate-500">
                  {{ new Date(order.created_at).toLocaleDateString(lang === 'en' ? 'en-US' : 'fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }) }}
                </td>
                <td class="py-4 font-mono font-bold text-slate-900">{{ order.reference }}</td>
                <td class="py-4 max-w-xs truncate">
                  <span class="font-bold text-slate-800">{{ order.product_name }}</span>
                  <span class="text-slate-400 ml-1">× {{ order.quantity }}</span>
                </td>
                <td class="py-4 font-bold text-[#0d5c4a]">
                  {{ format(order.total_xof || 0) }}
                </td>
                <td class="py-4">
                  <span
                    :class="[
                      'badge text-[11px]',
                      order.status === 'paid' || order.status === 'delivered' ? 'badge-success' : '',
                      order.status === 'pending_payment' ? 'badge-warning' : '',
                      order.status === 'failed' || order.status === 'payment_failed' ? 'badge-danger' : '',
                    ]"
                  >
                    {{ t(ORDER_STATUS_LABELS[order.status as keyof typeof ORDER_STATUS_LABELS] || order.status) }}
                  </span>
                </td>
                <td class="py-4 text-right">
                  <Link
                    :href="`/compte/achats/${order.id}`"
                    class="btn btn-soft btn-sm text-xs py-1 inline-flex items-center gap-1.5"
                  >
                    {{ t('Afficher') }}
                    <ArrowRight class="size-3.5" />
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="text-center py-12 text-xs text-slate-400 space-y-3">
          <Package class="size-10 mx-auto text-slate-300" />
          <p>{{ t("Aucun achat n'a été effectué pour le moment.") }}</p>
          <Link href="/boutique" class="btn btn-primary btn-sm inline-flex">
            {{ t('Visiter la boutique') }}
          </Link>
        </div>
      </div>
    </div>
  </AccountLayout>
</template>
