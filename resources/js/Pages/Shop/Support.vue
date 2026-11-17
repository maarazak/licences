<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, Mail } from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';

const page = usePage();
const user = page.props.auth?.user;
const supportEmail = (page.props as any).support_email || 'support@licences.pro';

const faq = [
  ["Je n'ai pas reçu mon code de connexion", "Vérifiez vos courriers indésirables, puis demandez un nouveau code depuis la page de connexion. Le dernier code envoyé est le seul valable."],
  ["Mon paiement est toujours en attente", "La commande démarre quand le prestataire confirme le paiement. Cela peut prendre quelques minutes selon l'opérateur. Depuis la commande, vous pouvez reprendre un paiement non terminé."],
  ["Mon compte a été débité mais la commande n'apparaît pas payée", "Ne payez pas une seconde fois. Vérifiez d'abord votre solde mobile money, puis ouvrez une demande d'assistance depuis la commande avec sa référence."],
  ["Où trouver mes accès ?", "Dans Mes accès et dans le détail de chaque commande, après connexion. Pour votre sécurité, les accès ne sont pas envoyés par e-mail."],
  ["Quelle garantie sur mon achat ?", "Elle dépend de chaque offre : elle est indiquée sur la fiche et rappelée avant le paiement. Un accès défaillant doit être signalé depuis la commande pendant la garantie."],
  ["Une commande payée n'a pas pu être livrée", "Vous choisissez depuis la commande entre un avoir immédiat sur votre solde et un remboursement sur votre moyen de paiement."],
];
</script>

<template>
  <SiteLayout>
    <Head title="Assistance" />

    <div class="container-page max-w-3xl py-10">
      <h1 class="h1">Assistance</h1>
      <p class="lead mt-3">Chaque demande est rattachée à votre compte et à votre commande, pour être suivie jusqu'au bout.</p>

      <div class="card mt-6 flex flex-wrap items-center justify-between gap-4 p-5">
        <div>
          <h2 class="h3">Ouvrir une demande</h2>
          <p class="mt-1 text-sm text-muted">N'indiquez jamais de mot de passe ni de code dans un message.</p>
        </div>
        <Link
          :href="user ? '/compte/assistance/nouveau' : '/connexion?next=%2Fcompte%2Fassistance%2Fnouveau'"
          class="btn btn-brand"
        >
          {{ user ? 'Nouvelle demande' : 'Se connecter pour faire une demande' }}
        </Link>
      </div>

      <h2 class="h2 mt-10">Questions fréquentes</h2>
      <div class="mt-4 space-y-2.5">
        <details
          v-for="[question, answer] in faq"
          :key="question"
          class="card group px-5 py-4"
        >
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-bold">
            {{ question }}
            <ChevronDown class="size-4 shrink-0 text-muted transition-transform group-open:rotate-180" aria-hidden="true" />
          </summary>
          <p class="mt-2.5 text-sm leading-relaxed text-muted">{{ answer }}</p>
        </details>
      </div>

      <p v-if="supportEmail" class="mt-8 flex items-center gap-2 text-sm text-muted">
        <Mail class="size-4" aria-hidden="true" />
        Vous n'arrivez pas à vous connecter ? Écrivez à&nbsp;
        <a :href="`mailto:${supportEmail}`" class="link">
          {{ supportEmail }}
        </a>
      </p>
    </div>
  </SiteLayout>
</template>
