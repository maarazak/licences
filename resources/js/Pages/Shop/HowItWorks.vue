<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Package, ShoppingBag, Smartphone } from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';

const steps = [
  {
    icon: ShoppingBag,
    title: 'Choisissez votre offre',
    text: "Type d'accès (lien, code ou compte), durée, garantie et disponibilité sont affichés avant de payer."
  },
  {
    icon: Smartphone,
    title: 'Payez en FCFA',
    text: "Connectez-vous avec un code reçu par e-mail, puis payez par Mobile Money sur la page sécurisée du prestataire. La commande démarre quand le paiement est confirmé."
  },
  {
    icon: Package,
    title: 'Recevez votre accès',
    text: "Vos accès apparaissent dans Mes achats et un e-mail vous prévient. Si l'activation prend du temps, la commande affiche son état."
  },
];
</script>

<template>
  <SiteLayout>
    <Head title="Comment ça marche" />

    <div class="container-page max-w-3xl py-10">
      <h1 class="h1">Comment ça marche</h1>
      <p class="lead mt-3">Trois étapes, quelques minutes.</p>

      <ol class="mt-8 space-y-3">
        <li
          v-for="({ icon, title, text }, index) in steps"
          :key="title"
          class="card flex gap-4 p-5"
        >
          <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-bright to-brand text-white shadow-[0_14px_28px_-10px_rgb(13_92_74/0.7)]">
            <component :is="icon" class="size-6" aria-hidden="true" />
          </span>
          <div>
            <p class="text-xs font-bold text-accent-ink">Étape {{ index + 1 }}</p>
            <h2 class="h3 mt-0.5 text-lg">{{ title }}</h2>
            <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ text }}</p>
          </div>
        </li>
      </ol>

      <div class="card mt-6 p-5">
        <h2 class="h3">Et si la livraison échoue ?</h2>
        <p class="mt-1.5 text-sm leading-relaxed text-muted">
          Vous êtes prévenu par e-mail et vous choisissez, depuis votre commande, entre un remboursement et un avoir sur votre solde, utilisable pour une prochaine commande.
        </p>
      </div>

      <Link href="/boutique" class="btn btn-primary mt-8">
        Voir les offres
        <ArrowRight class="size-4" aria-hidden="true" />
      </Link>
    </div>
  </SiteLayout>
</template>
