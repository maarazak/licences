<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Bell, Trash2, ArrowRight } from 'lucide-vue-next';
import AccountLayout from '@/Layouts/AccountLayout.vue';

const props = defineProps<{
  alerts: any[];
}>();

const deleteAlert = (id: string) => {
  router.delete(`/compte/alertes/${id}`, {
    preserveScroll: true,
  });
};
</script>

<template>
  <AccountLayout>
    <Head title="Mes alertes de stock — Espace client" />

    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="h1 text-2xl">Mes alertes de réapprovisionnement</h1>
          <p class="text-xs text-slate-500 mt-1">
            Recevez un e-mail dès qu'un produit actuellement en rupture revient en stock.
          </p>
        </div>

        <Link href="/boutique" class="btn btn-soft btn-sm">
          Parcourir la boutique
        </Link>
      </div>

      <div class="card p-6 shadow-card space-y-4">
        <div v-if="alerts.length" class="divide-y divide-slate-100">
          <div
            v-for="alert in alerts"
            :key="alert.id"
            class="py-4 flex items-center justify-between gap-4"
          >
            <div class="space-y-1 min-w-0">
              <h3 class="font-bold text-slate-900 truncate text-sm">
                {{ alert.product?.name || 'Produit' }}
              </h3>
              <p class="text-xs text-slate-400">
                Alerte activée le {{ new Date(alert.created_at).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }) }}
              </p>
            </div>

            <button
              type="button"
              @click="deleteAlert(alert.id)"
              class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition"
              title="Supprimer cette alerte"
            >
              <Trash2 class="size-4" />
            </button>
          </div>
        </div>

        <div v-else class="text-center py-12 text-xs text-slate-400 space-y-3">
          <Bell class="size-10 mx-auto text-slate-300" />
          <p>Vous n'avez aucune alerte active pour le moment.</p>
        </div>
      </div>
    </div>
  </AccountLayout>
</template>
