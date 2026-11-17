<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
  BadgeCheck,
  KeyRound,
  LifeBuoy,
  RefreshCw,
  ShoppingBag,
  Smartphone,
  Package,
  Zap,
  ShieldCheck,
  ChevronDown,
  ArrowRight,
} from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import ProductCard from '@/Components/Shop/ProductCard.vue';
import PaymentLogos from '@/Components/Shared/PaymentLogos.vue';

interface ProductItem {
  id: string;
  slug: string;
  name: string;
  display_name?: string;
  category?: string;
  shop_category?: string;
  image_url?: string;
  price_xof: number;
  in_stock?: boolean;
  stock?: number;
  preorder?: boolean;
  expire_days?: number;
  warranty?: string;
  delivery_kind?: string;
}

const props = defineProps<{
  featured: ProductItem[];
  categories: Record<string, number>;
}>();

const reasons = [
  { icon: BadgeCheck, title: 'Prix final en FCFA', text: 'Aucun frais caché ni surcoût bancaire au moment de payer.' },
  { icon: KeyRound, title: 'Accès dans votre espace', text: 'Retrouvez vos clés, identifiants et licences à tout moment dans Mes achats.' },
  { icon: LifeBuoy, title: 'Support & Assistance réactifs', text: 'Une équipe dédiée à votre écoute par ticket pour résoudre chaque demande.' },
  { icon: RefreshCw, title: 'Garantie ou remboursement', text: 'Si un accès ne fonctionne pas durant sa période de garantie, nous le remplaçons.' },
];

const steps = [
  { icon: ShoppingBag, step: '01', title: 'Choisissez votre offre', text: 'Consultez la fiche détaillée : type d’accès, durée, compatibilité et avis.' },
  { icon: Smartphone, step: '02', title: 'Payez en FCFA', text: 'Réglez en toute sécurité avec Orange Money, Moov, MTN, Wave ou votre solde.' },
  { icon: Package, step: '03', title: 'Recevez votre accès', text: 'Livraison instantanée dans votre espace client, avec notification par e-mail.' },
];

const faqs = [
  {
    q: 'Combien de temps faut-il pour recevoir mon abonnement ?',
    a: 'La grande majorité des licences sont livrées en quelques secondes à quelques minutes après la validation de votre paiement. Pour les offres nécessitant une activation manuelle sur votre compte, le délai est de 15 à 45 minutes.',
  },
  {
    q: 'Quels sont les moyens de paiement acceptés ?',
    a: 'Nous acceptons les principaux services Mobile Money : Orange Money, Moov Money, MTN Mobile Money et Wave, ainsi que le solde de votre compte.',
  },
  {
    q: 'Que se passe-t-il si mon compte ou ma clé rencontre un problème ?',
    a: 'Toutes nos offres bénéficient d’une garantie de remplacement. Rendez-vous dans votre espace client pour ouvrir un ticket : notre équipe procède au remplacement immédiat ou au crédit de votre solde.',
  },
  {
    q: 'Puis-je recharger mon solde pour commander plus vite ?',
    a: 'Oui ! Vous pouvez approvisionner votre solde en FCFA via Mobile Money et commander en un clic sans repasser par la passerelle de paiement.',
  },
];

const openFaq = ref<number | null>(null);
const toggleFaq = (index: number) => {
  openFaq.value = openFaq.value === index ? null : index;
};
</script>

<template>
  <SiteLayout>
    <Head title="Abonnements et licences numériques, payés en FCFA" />

    <!-- ── HÉROS ── -->
    <section class="bg-hero relative overflow-hidden border-b border-slate-200/80">
      <div aria-hidden="true" class="grid-lines absolute inset-0" />
      <div class="container-page relative grid gap-12 py-14 sm:py-18 lg:grid-cols-[1.1fr_0.9fr] lg:items-center lg:gap-10 lg:py-20">
        <div class="animate-fade-up">
          <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
            <p class="inline-flex items-center gap-2.5 rounded-full border border-slate-200 bg-white px-4 py-1.5 text-xs font-bold text-slate-800 shadow-sm">
              <span class="pulse-dot size-2 rounded-full bg-[#19c39a]" />
              Livraison en quelques minutes
            </p>
            <PaymentLogos layout="floating" />
          </div>

          <h1 class="mt-6 font-display text-[2.6rem] leading-[1.06] font-extrabold tracking-tight text-slate-900 sm:text-6xl lg:text-[3.8rem]">
            Vos abonnements premium, <span class="text-gradient">payés en FCFA.</span>
          </h1>

          <p class="mt-6 max-w-lg text-lg leading-relaxed text-slate-600">
            Netflix, ChatGPT, Claude, Canva, Prime Video et bien plus. Payez facilement par Mobile Money, recevez votre accès instantanément.
          </p>

          <div class="mt-8 flex flex-wrap gap-3">
            <a href="#phares" class="btn btn-primary min-h-12 px-7 text-base shadow-md">
              Voir les offres phares
            </a>
            <Link href="/boutique" class="btn btn-soft min-h-12 px-7 text-base">
              Toute la boutique
            </Link>
          </div>

          <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm font-semibold text-slate-700">
            <li class="flex items-center gap-2">
              <Zap class="size-4 text-[#ff6a13]" />
              <span>Livraison instantanée</span>
            </li>
            <li class="flex items-center gap-2">
              <ShieldCheck class="size-4 text-[#0d5c4a]" />
              <span>Paiement 100% sécurisé</span>
            </li>
            <li class="flex items-center gap-2">
              <RefreshCw class="size-4 text-emerald-600" />
              <span>Garantie de remplacement</span>
            </li>
          </ul>
        </div>

        <!-- Featured hero showcase preview -->
        <div class="hidden lg:grid grid-cols-2 gap-4 animate-fade-up delay-1">
          <ProductCard
            v-for="product in featured.slice(0, 4)"
            :key="product.id"
            :product="product"
            :priority="true"
          />
        </div>
      </div>
    </section>

    <!-- ── CATÉGORIES POPULAIRES ── -->
    <section v-if="Object.keys(categories).length" class="py-8 bg-white border-b border-slate-200/80">
      <div class="container-page flex items-center gap-3 overflow-x-auto pb-2 scrollbar-none">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider shrink-0 mr-2">
          Rayons :
        </span>
        <Link
          href="/boutique"
          class="chip chip-active shrink-0 text-xs py-1.5"
        >
          Tous les produits
        </Link>
        <Link
          v-for="(count, cat) in categories"
          :key="cat"
          :href="`/boutique?categorie=${encodeURIComponent(cat)}`"
          class="chip shrink-0 text-xs py-1.5"
        >
          {{ cat.toUpperCase() }} ({{ count }})
        </Link>
      </div>
    </section>

    <!-- ── PRODUITS PHARES ── -->
    <section id="phares" class="py-16 sm:py-20">
      <div class="container-page space-y-10">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
          <div>
            <span class="eyebrow text-[#0d5c4a]">Catalogue exclusif</span>
            <h2 class="h2 mt-1">Les abonnements les plus demandés</h2>
            <p class="lead mt-1">Activations certifiées et prix les plus bas en Afrique de l'Ouest et Centrale.</p>
          </div>
          <Link href="/boutique" class="btn btn-soft shrink-0 self-start sm:self-auto">
            Voir tout le catalogue ({{ featured.length }}+)
            <ArrowRight class="size-4 ml-1" />
          </Link>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <ProductCard
            v-for="product in featured"
            :key="product.id"
            :product="product"
          />
        </div>
      </div>
    </section>

    <!-- ── COMMENT ÇA MARCHE ── -->
    <section class="py-16 sm:py-20 bg-white border-y border-slate-200/80">
      <div class="container-page space-y-12">
        <div class="text-center max-w-2xl mx-auto space-y-2">
          <span class="eyebrow text-[#0d5c4a]">Simple et rapide</span>
          <h2 class="h2">Comment fonctionne votre commande ?</h2>
          <p class="lead">3 étapes simples pour activer vos outils numériques en quelques minutes.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div
            v-for="s in steps"
            :key="s.step"
            class="card p-8 relative flex flex-col items-center text-center space-y-4 hover:shadow-pop transition duration-300"
          >
            <div class="size-16 rounded-2xl bg-[#0d5c4a]/10 text-[#0d5c4a] flex items-center justify-center font-bold">
              <component :is="s.icon" class="size-8" />
            </div>
            <span class="text-xs font-mono font-bold text-slate-400 tracking-wider">ÉTAPE {{ s.step }}</span>
            <h3 class="h3 text-lg font-bold">{{ s.title }}</h3>
            <p class="text-sm text-slate-500 leading-relaxed">{{ s.text }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ── POURQUOI NOUS CHOISIR ── -->
    <section class="py-16 sm:py-20">
      <div class="container-page space-y-12">
        <div class="text-center max-w-2xl mx-auto space-y-2">
          <span class="eyebrow text-[#0d5c4a]">Nos garanties</span>
          <h2 class="h2">Achetez l'esprit tranquille</h2>
          <p class="lead">Une plateforme conçue sur-mesure pour les utilisateurs africains.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div
            v-for="r in reasons"
            :key="r.title"
            class="card p-6 space-y-3 hover:-translate-y-1 transition duration-200"
          >
            <div class="size-12 rounded-xl bg-slate-100 flex items-center justify-center text-[#0d5c4a]">
              <component :is="r.icon" class="size-6" />
            </div>
            <h3 class="font-bold text-slate-900">{{ r.title }}</h3>
            <p class="text-xs text-slate-500 leading-relaxed">{{ r.text }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ── FAQ ACCORDION ── -->
    <section class="py-16 sm:py-20 bg-white border-t border-slate-200/80">
      <div class="container-page max-w-3xl space-y-10">
        <div class="text-center space-y-2">
          <span class="eyebrow text-[#0d5c4a]">Questions fréquentes</span>
          <h2 class="h2">Tout ce que vous devez savoir</h2>
        </div>

        <div class="space-y-3">
          <div
            v-for="(faq, i) in faqs"
            :key="i"
            class="card overflow-hidden border border-slate-200/80 transition"
          >
            <button
              type="button"
              @click="toggleFaq(i)"
              class="w-full flex items-center justify-between p-5 text-left font-bold text-slate-900 hover:text-[#0d5c4a] transition"
            >
              <span class="text-base">{{ faq.q }}</span>
              <ChevronDown
                :class="[
                  'size-5 text-slate-400 shrink-0 transition-transform duration-200',
                  openFaq === i ? 'rotate-180 text-[#0d5c4a]' : '',
                ]"
              />
            </button>
            <div
              v-if="openFaq === i"
              class="px-5 pb-5 pt-1 text-sm text-slate-600 leading-relaxed border-t border-slate-100/80 animate-fade-up"
            >
              {{ faq.a }}
            </div>
          </div>
        </div>
      </div>
    </section>
  </SiteLayout>
</template>
