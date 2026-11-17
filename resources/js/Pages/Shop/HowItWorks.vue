<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ShoppingBag, Smartphone, Package, ArrowRight, ShieldCheck } from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';

const steps = [
  {
    icon: ShoppingBag,
    title: 'Choisissez votre offre',
    text: 'Type d’accès (compte fourni, lien d’invitation ou code d’activation), durée, garantie et disponibilité sont indiqués en toute transparence.',
  },
  {
    icon: Smartphone,
    title: 'Payez en Francs CFA',
    text: 'Payez en toute sécurité par Mobile Money (Orange Money, Moov, MTN, Wave) ou directement par votre solde. La commande est validée dès confirmation.',
  },
  {
    icon: Package,
    title: 'Recevez votre accès',
    text: 'Vos accès apparaissent immédiatement dans votre espace client (Mes achats) et un e-mail de confirmation vous est envoyé.',
  },
];
</script>

<template>
  <SiteLayout>
    <Head title="Comment ça marche — Fonctionnement" />

    <div class="container-page max-w-3xl py-12 space-y-8">
      <div>
        <h1 class="h1">Comment ça marche</h1>
        <p class="lead mt-2">Trois étapes simples, quelques minutes pour être opérationnel.</p>
      </div>

      <ol class="space-y-4">
        <li
          v-for="(s, index) in steps"
          :key="s.title"
          class="card flex gap-5 p-6 hover:shadow-pop transition duration-200"
        >
          <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#19c39a] to-[#0d5c4a] text-white shadow-md">
            <component :is="s.icon" class="size-7" />
          </span>
          <div class="space-y-1">
            <p class="text-xs font-bold text-[#e85a07] uppercase tracking-wider">Étape {{ index + 1 }}</p>
            <h2 class="h3 text-lg font-bold text-slate-900">{{ s.title }}</h2>
            <p class="text-sm leading-relaxed text-slate-600">{{ s.text }}</p>
          </div>
        </li>
      </ol>

      <div class="card p-6 bg-gradient-to-br from-white to-teal-50/50 border-teal-200/80 space-y-2">
        <h2 class="h3 text-base text-[#0d5c4a] flex items-center gap-2">
          <ShieldCheck class="size-5" />
          Et si la livraison échoue ou tarde ?
        </h2>
        <p class="text-sm leading-relaxed text-slate-600">
          Vous êtes protégé par notre garantie : notre équipe intervient immédiatement pour vous attribuer un nouvel accès valide ou créditer l'intégralité du montant sur votre solde client.
        </p>
      </div>

      <div class="pt-4">
        <Link href="/boutique" class="btn btn-primary min-h-12 px-7 text-base shadow-glow">
          Découvrir les offres
          <ArrowRight class="size-4 ml-1" />
        </Link>
      </div>
    </div>
  </SiteLayout>
</template>
