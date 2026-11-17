<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PackageOpen, ArrowRight } from 'lucide-vue-next';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import CopyButton from '@/Components/Shared/CopyButton.vue';
import { formatDate } from '@/Utils/format';

interface DeliveredOrder {
  id: string;
  reference: string;
  product_name: string;
  delivered_at?: string;
  items: string[];
}

defineProps<{
  orders: DeliveredOrder[];
}>();
</script>

<template>
  <AccountLayout>
    <Head title="Mes accès" />

    <div class="space-y-6">
      <div>
        <h1 class="h2 text-2xl font-bold text-ink">Mes accès</h1>
        <p class="text-xs text-muted mt-1 leading-relaxed">
          Réservé au propriétaire connecté de chaque commande. Un numéro de commande seul n'y donne jamais accès.
        </p>
      </div>

      <div v-if="orders.length > 0" class="space-y-6">
        <section
          v-for="order in orders"
          :key="order.id"
          class="card p-5 space-y-4"
        >
          <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line pb-3">
            <h2 class="h3 text-base font-bold text-ink">{{ order.product_name }}</h2>
            <Link :href="`/compte/achats/${order.id}`" class="link text-xs">
              {{ order.reference }} · livrée le {{ formatDate(order.delivered_at ? new Date(order.delivered_at) : null) }}
            </Link>
          </div>

          <ul v-if="order.items.length > 0" class="space-y-2">
            <li
              v-for="(item, idx) in order.items"
              :key="idx"
              class="flex items-start justify-between gap-3 rounded-xl border border-line bg-canvas p-3"
            >
              <code class="min-w-0 flex-1 font-mono text-sm leading-relaxed whitespace-pre-wrap break-all text-ink">
                {{ item }}
              </code>
              <CopyButton :value="item" />
            </li>
          </ul>

          <p v-else class="alert alert-warning text-xs">
            Accès indisponibles pour le moment. Contactez l'assistance depuis la commande.
          </p>
        </section>
      </div>

      <!-- Empty state -->
      <div v-else class="card p-10 text-center space-y-4">
        <div class="size-16 rounded-2xl bg-teal-50 text-brand flex items-center justify-center mx-auto">
          <PackageOpen class="size-8" />
        </div>
        <div class="space-y-1">
          <h2 class="h3 text-lg font-bold text-ink">Aucun accès disponible pour l'instant</h2>
          <p class="text-xs text-muted max-w-sm mx-auto leading-relaxed">
            Vos accès apparaîtront ici dès confirmation du paiement et livraison automatique.
          </p>
        </div>
        <div class="pt-2">
          <Link href="/compte/achats" class="btn btn-primary min-h-11 text-xs">
            Voir mes achats
            <ArrowRight class="size-3.5 ml-1" />
          </Link>
        </div>
      </div>
    </div>
  </AccountLayout>
</template>
