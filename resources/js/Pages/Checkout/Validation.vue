<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
  ShieldCheck,
  Lock,
  ArrowRight,
  Wallet,
  Smartphone,
  Minus,
  Plus,
  AlertCircle,
  Zap,
} from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import BrandLogo from '@/Components/Shared/BrandLogo.vue';
import PaymentLogos from '@/Components/Shared/PaymentLogos.vue';
import { COUNTRIES } from '@/Utils/countries';

interface ProductItem {
  id: string;
  slug: string;
  name: string;
  display_name?: string;
  category?: string;
  shop_category?: string;
  image_url?: string;
  price_xof: number;
  delivery_kind?: string;
  requires_email?: boolean;
}

const props = defineProps<{
  product: ProductItem;
  clientUser?: {
    id: string;
    email: string;
    balance_xof: number;
    country?: string;
    phone?: string;
  } | null;
}>();

const title = computed(() => props.product.display_name || props.product.name);
const quantity = ref(1);
const selectedCountry = ref(props.clientUser?.country || 'BF');

const currentCountryInfo = computed(() => {
  return COUNTRIES.find((c) => c.code === selectedCountry.value) || COUNTRIES[0];
});

const form = useForm({
  product_id: props.product.id,
  quantity: 1,
  email: props.clientUser?.email || '',
  phone: props.clientUser?.phone || '',
  country: selectedCountry.value,
  paid_with: 'pawapay', // 'pawapay' or 'balance'
  custom_fields: {} as Record<string, any>,
});

const totalXof = computed(() => props.product.price_xof * quantity.value);
const userBalance = computed(() => props.clientUser?.balance_xof || 0);
const canPayWithBalance = computed(() => userBalance.value >= totalXof.value);

const updateQuantity = (newQty: number) => {
  if (newQty >= 1 && newQty <= 10) {
    quantity.value = newQty;
    form.quantity = newQty;
    if (form.paid_with === 'balance' && !canPayWithBalance.value) {
      form.paid_with = 'pawapay';
    }
  }
};

const submitOrder = () => {
  form.country = selectedCountry.value;
  form.post('/commande/validation', {
    preserveScroll: true,
  });
};
</script>

<template>
  <SiteLayout>
    <Head title="Valider votre commande — Paiement sécurisé" />

    <div class="container-page py-10 max-w-4xl space-y-8">
      <div>
        <h1 class="h1 text-2xl sm:text-3xl">Finaliser votre commande</h1>
        <p class="lead text-sm mt-1">
          Réglez en toute sécurité par Mobile Money ou avec votre solde en Francs CFA.
        </p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <!-- Left: Form inputs -->
        <div class="lg:col-span-7 space-y-6">
          <!-- Item Card in order -->
          <div class="card p-5 flex items-center justify-between gap-4">
            <div class="flex items-center gap-4 min-w-0">
              <BrandLogo :name="title" :image="product.image_url" size="md" />
              <div class="min-w-0">
                <h3 class="font-bold text-slate-900 truncate">{{ title }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">
                  {{ (product.price_xof || 0).toLocaleString('fr-FR') }} FCFA / unité
                </p>
              </div>
            </div>

            <!-- Quantity stepper -->
            <div class="flex items-center gap-2 border border-slate-200 rounded-xl p-1 bg-slate-50">
              <button
                type="button"
                @click="updateQuantity(quantity - 1)"
                :disabled="quantity <= 1"
                class="size-7 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-600 disabled:opacity-40 hover:bg-slate-100 transition"
              >
                <Minus class="size-3.5" />
              </button>
              <span class="w-6 text-center text-sm font-bold text-slate-800">{{ quantity }}</span>
              <button
                type="button"
                @click="updateQuantity(quantity + 1)"
                :disabled="quantity >= 10"
                class="size-7 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-600 disabled:opacity-40 hover:bg-slate-100 transition"
              >
                <Plus class="size-3.5" />
              </button>
            </div>
          </div>

          <!-- Customer details -->
          <div class="card p-6 space-y-5">
            <h2 class="h3 text-base flex items-center gap-2">
              <span class="size-6 rounded-full bg-[#0d5c4a]/10 text-[#0d5c4a] text-xs flex items-center justify-center font-bold">1</span>
              Vos coordonnées
            </h2>

            <div class="space-y-4 text-sm">
              <div>
                <label class="label text-xs">Adresse e-mail de réception <span class="text-red-500">*</span></label>
                <input
                  v-model="form.email"
                  type="email"
                  required
                  placeholder="votre.email@gmail.com"
                  class="input"
                  :disabled="!!clientUser"
                />
                <p class="hint">Vos identifiants et le reçu de paiement y seront immédiatement envoyés.</p>
              </div>

              <div>
                <label class="label text-xs">Pays de paiement <span class="text-red-500">*</span></label>
                <select
                  v-model="selectedCountry"
                  class="input font-medium"
                >
                  <option
                    v-for="c in COUNTRIES"
                    :key="c.code"
                    :value="c.code"
                  >
                    {{ c.flag }} {{ c.name }} ({{ c.dial }})
                  </option>
                </select>
              </div>

              <div>
                <label class="label text-xs">Numéro de téléphone Mobile Money</label>
                <div class="relative flex items-center">
                  <span class="absolute left-3.5 text-xs font-mono font-bold text-slate-500">
                    {{ currentCountryInfo.dial }}
                  </span>
                  <input
                    v-model="form.phone"
                    type="tel"
                    :placeholder="currentCountryInfo.placeholder"
                    class="input pl-16 font-mono"
                  />
                </div>
                <p class="hint">Numéro lié à votre compte Orange Money, Moov, MTN ou Wave.</p>
              </div>
            </div>
          </div>

          <!-- Payment Choice -->
          <div class="card p-6 space-y-5">
            <h2 class="h3 text-base flex items-center gap-2">
              <span class="size-6 rounded-full bg-[#0d5c4a]/10 text-[#0d5c4a] text-xs flex items-center justify-center font-bold">2</span>
              Moyen de règlement
            </h2>

            <div class="space-y-3">
              <!-- Mobile Money option -->
              <label
                :class="[
                  'flex items-start gap-4 p-4 rounded-2xl border cursor-pointer transition',
                  form.paid_with === 'pawapay'
                    ? 'border-[#0d5c4a] bg-teal-50/40 ring-2 ring-[#0d5c4a]/20'
                    : 'border-slate-200 bg-white hover:bg-slate-50',
                ]"
              >
                <input
                  v-model="form.paid_with"
                  type="radio"
                  value="pawapay"
                  class="mt-1 size-4 accent-[#0d5c4a]"
                />
                <div class="flex-1 space-y-1">
                  <div class="flex items-center justify-between">
                    <span class="font-bold text-slate-900 text-sm">Mobile Money (pawaPay)</span>
                    <span class="badge badge-success text-[10px]">Recommandé</span>
                  </div>
                  <p class="text-xs text-slate-500">
                    Orange Money, Moov Money, MTN MoMo, Wave. Validation instantanée.
                  </p>
                  <div class="pt-2">
                    <PaymentLogos layout="row" />
                  </div>
                </div>
              </label>

              <!-- Wallet Balance option -->
              <label
                :class="[
                  'flex items-start gap-4 p-4 rounded-2xl border transition',
                  canPayWithBalance ? 'cursor-pointer' : 'opacity-60 cursor-not-allowed bg-slate-50',
                  form.paid_with === 'balance'
                    ? 'border-[#0d5c4a] bg-teal-50/40 ring-2 ring-[#0d5c4a]/20'
                    : 'border-slate-200 bg-white hover:bg-slate-50',
                ]"
              >
                <input
                  v-model="form.paid_with"
                  type="radio"
                  value="balance"
                  :disabled="!canPayWithBalance"
                  class="mt-1 size-4 accent-[#0d5c4a]"
                />
                <div class="flex-1 space-y-1">
                  <div class="flex items-center justify-between">
                    <span class="font-bold text-slate-900 text-sm">Solde du compte</span>
                    <span class="text-xs font-mono font-bold text-[#0d5c4a]">
                      {{ userBalance.toLocaleString('fr-FR') }} FCFA disponible
                    </span>
                  </div>
                  <p class="text-xs text-slate-500">
                    Débit direct sans redirection. Livraison immédiate.
                  </p>
                  <p v-if="!canPayWithBalance" class="text-[11px] text-amber-700 font-medium pt-1">
                    Solde insuffisant pour ce panier ({{ totalXof.toLocaleString('fr-FR') }} FCFA requis).
                  </p>
                </div>
              </label>
            </div>
          </div>
        </div>

        <!-- Right: Order summary & Submit -->
        <div class="lg:col-span-5 sticky top-24">
          <div class="card p-6 space-y-6 shadow-pop">
            <h2 class="h3 text-base border-b border-slate-100 pb-3">Récapitulatif</h2>

            <div class="space-y-3 text-sm">
              <div class="flex justify-between text-slate-600">
                <span>{{ title }} × {{ quantity }}</span>
                <span class="font-semibold text-slate-900">{{ totalXof.toLocaleString('fr-FR') }} FCFA</span>
              </div>
              <div class="flex justify-between text-slate-600 text-xs">
                <span>Frais de traitement</span>
                <span class="text-emerald-600 font-bold">Gratuit (0 FCFA)</span>
              </div>
              <div class="flex justify-between text-slate-600 text-xs">
                <span>Délai de livraison</span>
                <span class="font-medium text-slate-800">Quelques minutes</span>
              </div>

              <div class="pt-3 border-t border-slate-100 flex justify-between items-baseline">
                <span class="font-bold text-slate-900 text-base">Total à payer</span>
                <span class="font-display text-2xl font-extrabold text-[#0d5c4a]">
                  {{ totalXof.toLocaleString('fr-FR') }} FCFA
                </span>
              </div>
            </div>

            <button
              type="button"
              @click="submitOrder"
              :disabled="form.processing"
              class="btn btn-primary btn-block min-h-12 text-base shadow-glow"
            >
              <Lock class="size-4" />
              <span v-if="form.processing">Traitement en cours...</span>
              <span v-else-if="form.paid_with === 'balance'">Confirmer et payer par solde</span>
              <span v-else>Payer {{ totalXof.toLocaleString('fr-FR') }} FCFA</span>
            </button>

            <div class="space-y-2 pt-2 text-[11px] text-slate-500 text-center leading-relaxed">
              <p class="flex items-center justify-center gap-1.5 font-medium text-slate-600">
                <ShieldCheck class="size-4 text-[#0d5c4a]" />
                Transaction sécurisée avec pawaPay
              </p>
              <p>
                En confirmant, vous acceptez les conditions générales de vente et la garantie de remplacement.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </SiteLayout>
</template>
