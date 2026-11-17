<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Package, ShoppingBag, Smartphone } from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import { useI18n } from '@/Utils/useI18n';

const { t } = useI18n();

const steps = computed(() => [
  {
    icon: ShoppingBag,
    title: t('Choisissez votre offre'),
    text: t("Type d'accès (lien, code ou compte), durée, garantie et disponibilité sont affichés avant de payer.")
  },
  {
    icon: Smartphone,
    title: t('Payez en FCFA'),
    text: t("Connectez-vous avec un code reçu par e-mail, puis payez par Mobile Money sur la page sécurisée du prestataire. La commande démarre quand le paiement est confirmé.")
  },
  {
    icon: Package,
    title: t('Recevez votre accès'),
    text: t("Vos accès apparaissent dans Mes achats et un e-mail vous prévient. Si l'activation prend du temps, la commande affiche son état.")
  },
]);
</script>

<template>
  <SiteLayout>
    <Head :title="t('Comment ça marche')" />

    <div class="container-page max-w-3xl py-10">
      <h1 class="h1">{{ t('Comment ça marche') }}</h1>
      <p class="lead mt-3">{{ t('Trois étapes, quelques minutes.') }}</p>

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
            <p class="text-xs font-bold text-accent-ink">{{ t('Étape {n}', { n: index + 1 }) }}</p>
            <h2 class="h3 mt-0.5 text-lg">{{ title }}</h2>
            <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ text }}</p>
          </div>
        </li>
      </ol>

      <div class="card mt-6 p-5">
        <h2 class="h3">{{ t('Et si la livraison échoue ?') }}</h2>
        <p class="mt-1.5 text-sm leading-relaxed text-muted">
          {{ t('Vous êtes prévenu par e-mail et vous choisissez, depuis votre commande, entre un remboursement et un avoir sur votre solde, utilisable pour une prochaine commande.') }}
        </p>
      </div>

      <Link href="/boutique" class="btn btn-primary mt-8">
        {{ t('Voir les offres') }}
        <ArrowRight class="size-4" aria-hidden="true" />
      </Link>
    </div>
  </SiteLayout>
</template>
