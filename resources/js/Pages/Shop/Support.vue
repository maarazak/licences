<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronDown, Mail, LifeBuoy, ArrowRight } from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';

const faq = [
  ['Je n\'ai pas reçu mon code de connexion', 'Vérifiez d\'abord votre dossier de courriers indésirables (spams). Si le code n\'apparaît pas, demandez-en un nouveau depuis la page de connexion.'],
  ['Mon paiement est toujours en attente', 'La commande est confirmée dès que l\'opérateur Mobile Money valide la transaction. Cela prend généralement entre 30 secondes et 2 minutes. Vous pouvez reprendre la session depuis votre commande.'],
  ['Mon compte a été débité mais la commande n\'apparaît pas payée', 'Ne payez pas une seconde fois. Ouvrez un ticket d\'assistance avec votre référence de transaction : notre équipe procède à la vérification et valide manuellement votre commande.'],
  ['Où trouver mes accès livrés ?', 'Dans votre espace client, sous la rubrique « Mes achats », en cliquant sur le détail de votre commande.'],
  ['Quelle garantie est appliquée sur mon achat ?', 'Chaque offre comporte une période de garantie clairement indiquée sur la fiche produit (30 jours, 6 mois, 1 an). Pendant toute cette période, nous assurons le remplacement en cas de problème.'],
];
</script>

<template>
  <SiteLayout>
    <Head title="Assistance & FAQ — Support client" />

    <div class="container-page max-w-3xl py-12 space-y-8">
      <div>
        <h1 class="h1">Centre d'aide & Assistance</h1>
        <p class="lead mt-2">
          Retrouvez les réponses aux questions les plus courantes ou contactez directement notre équipe de support.
        </p>
      </div>

      <!-- Action Card -->
      <div class="card p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-teal-50/50 border-teal-200/80">
        <div>
          <h2 class="h3 text-base text-[#0d5c4a]">Besoin d'une aide personnalisée ?</h2>
          <p class="text-xs text-slate-600 mt-1">
            Chaque demande est suivie individuellement jusqu'à sa résolution complète.
          </p>
        </div>
        <Link
          href="/compte/assistance/nouveau"
          class="btn btn-primary btn-sm shrink-0 self-start sm:self-auto shadow-sm"
        >
          <LifeBuoy class="size-4" />
          Ouvrir une demande
        </Link>
      </div>

      <!-- FAQ Section -->
      <div class="space-y-4">
        <h2 class="h2 text-xl">Foire aux questions</h2>

        <div class="space-y-3">
          <details
            v-for="([q, a], idx) in faq"
            :key="idx"
            class="card group px-5 py-4 cursor-pointer"
          >
            <summary class="flex items-center justify-between font-bold text-slate-900 text-sm list-none">
              <span>{{ q }}</span>
              <ChevronDown class="size-4 text-slate-400 shrink-0 transition-transform group-open:rotate-180" />
            </summary>
            <p class="mt-3 text-xs leading-relaxed text-slate-600 border-t border-slate-100 pt-3">
              {{ a }}
            </p>
          </details>
        </div>
      </div>
    </div>
  </SiteLayout>
</template>
