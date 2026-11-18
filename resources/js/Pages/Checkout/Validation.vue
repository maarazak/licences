<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
  ArrowRight,
  Lock,
  Minus,
  Plus,
  Smartphone,
  CreditCard,
  Coins,
  Wallet,
  Sparkles,
  ShieldCheck,
  Zap
} from 'lucide-vue-next';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import BrandLogo from '@/Components/Shared/BrandLogo.vue';
import { COUNTRIES, type CountryCode, getCountry, validatePhoneForCountry } from '@/Utils/countries';
import { formatXof, formatCurrency } from '@/Utils/format';
import { useI18n } from '@/Utils/useI18n';

interface ProductItem {
  id: string;
  slug: string;
  name: string;
  title: string;
  unitPriceXof: number;
  available: boolean;
  stock?: number;
  maxQuantity: number;
  requiresEmail: boolean;
  accessLabel: string;
  durationLabel: string;
  warranty?: string;
}

const props = defineProps<{
  clientToken: string;
  product: ProductItem;
  defaults: {
    fullName: string;
    firstName: string;
    lastName: string;
    phone: string;
    country: string;
    email: string;
  };
  balanceXof: number;
}>();

const { t, formatPrice, currency } = useI18n();

const quantity = ref(1);
const selectedCountry = ref<CountryCode>((props.defaults.country && COUNTRIES.some(c => c.code === props.defaults.country) ? props.defaults.country : 'BF') as CountryCode);
const payChoice = ref<'cinetpay' | 'cryptomus' | 'balance'>('cinetpay');

const currentCountry = computed(() => getCountry(selectedCountry.value));

const totalXof = computed(() => props.product.unitPriceXof * quantity.value);
const canUseBalance = computed(() => props.balanceXof >= totalXof.value);
const effectivePayWith = computed(() => {
  if (payChoice.value === 'balance') {
    return canUseBalance.value ? 'balance' : 'cinetpay';
  }
  return payChoice.value;
});

const activationEmails = ref<string[]>(Array.from({ length: 1 }, (_, i) => (i === 0 ? props.defaults.email : '')));

const updateQuantity = (newQty: number) => {
  const max = props.product.maxQuantity || 10;
  const clamped = Math.max(1, Math.min(newQty, max));
  quantity.value = clamped;

  if (props.product.requiresEmail) {
    const current = [...activationEmails.value];
    while (current.length < clamped) {
      current.push('');
    }
    activationEmails.value = current.slice(0, clamped);
  }
};

const form = useForm({
  productId: props.product.id,
  quantity: 1,
  fullName: props.defaults.fullName || '',
  phone: '',
  country: selectedCountry.value,
  expectedTotalXof: totalXof.value,
  clientToken: props.clientToken,
  activationEmails: [] as string[],
  payWith: 'gateway',
});

const submitCheckout = () => {
  form.quantity = quantity.value;
  form.phone = '';
  form.country = selectedCountry.value;
  form.expectedTotalXof = totalXof.value;
  form.activationEmails = props.product.requiresEmail ? activationEmails.value : [];
  form.payWith = effectivePayWith.value;

  form.post('/commande/validation', {
    preserveScroll: true,
  });
};
</script>

<template>
  <SiteLayout>
    <Head title="Valider votre commande" />

    <div class="container-page py-10">
      <h1 class="h1 mb-8">Valider votre commande</h1>

      <form @submit.prevent="submitCheckout" class="grid gap-8 lg:grid-cols-[1fr_360px] lg:items-start">
        <div class="min-w-0 space-y-8">
          <!-- Coordonnées -->
          <section class="card p-5 sm:p-6">
            <h2 class="h3 text-lg">Vos coordonnées</h2>
            <p class="hint">Transmises au prestataire de paiement pour cet achat.</p>
            <p class="mt-3 text-sm break-all">
              <span class="text-muted">Compte : </span>
              <span class="font-semibold text-ink">{{ defaults.email }}</span>
            </p>

            <div class="mt-4 space-y-4">
              <div>
                <label for="fullName" class="label">
                  Nom complet <span class="text-brand">*</span>
                </label>
                <input
                  id="fullName"
                  v-model="form.fullName"
                  required
                  autocomplete="name"
                  maxlength="150"
                  placeholder="Ex. Awa Traoré"
                  class="input"
                />
              </div>

              <div>
                <label for="country" class="label">
                  Pays de facturation
                </label>
                <select
                  id="country"
                  v-model="selectedCountry"
                  class="input cursor-pointer"
                >
                  <option v-for="c in COUNTRIES" :key="c.code" :value="c.code">
                    {{ c.flag }} {{ c.name }} ({{ c.dial }})
                  </option>
                </select>
              </div>
            </div>
          </section>

          <!-- Quantité -->
          <section class="card p-5 sm:p-6">
            <h2 class="h3 text-lg">Quantité</h2>
            <div class="mt-3 flex items-center gap-2 max-w-44">
              <button
                type="button"
                @click="updateQuantity(quantity - 1)"
                :disabled="quantity <= 1"
                class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-line bg-canvas text-base font-bold text-ink transition hover:border-brand/40 disabled:opacity-40"
                aria-label="Diminuer la quantité"
              >
                −
              </button>
              <input
                id="quantity"
                type="number"
                min="1"
                :max="product.maxQuantity"
                step="1"
                required
                :value="quantity"
                @change="(e: any) => updateQuantity(parseInt(e.target.value, 10) || 1)"
                class="input h-11 text-center text-base font-semibold"
              />
              <button
                type="button"
                @click="updateQuantity(quantity + 1)"
                :disabled="quantity >= product.maxQuantity"
                class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-line bg-canvas text-base font-bold text-ink transition hover:border-brand/40 disabled:opacity-40"
                aria-label="Augmenter la quantité"
              >
                +
              </button>
            </div>

            <!-- Adresses d'activation si requis -->
            <div v-if="product.requiresEmail" class="mt-6">
              <h3 class="h3">{{ quantity > 1 ? 'Adresses e-mail à activer' : 'Adresse e-mail à activer' }}</h3>
              <p class="hint">
                Cette offre s'active sur une adresse e-mail. Indiquez-en une par unité, toutes différentes. Vérifiez l'orthographe : elle ne peut pas être changée après le paiement.
              </p>
              <div class="mt-3 space-y-3">
                <div v-for="(_, i) in quantity" :key="i">
                  <label :for="`activation-${i}`" class="sr-only">
                    Adresse e-mail à activer n° {{ i + 1 }}
                  </label>
                  <input
                    :id="`activation-${i}`"
                    v-model="activationEmails[i]"
                    type="email"
                    required
                    maxlength="191"
                    :placeholder="`Adresse n° ${i + 1}`"
                    class="input"
                  />
                </div>
              </div>
            </div>
          </section>

          <!-- Moyen de paiement -->
          <section class="card p-6 sm:p-7 shadow-card">
            <div class="flex items-center justify-between gap-3 border-b border-line/80 pb-4">
              <div>
                <h2 class="h3 text-xl text-ink font-extrabold flex items-center gap-2.5">
                  <ShieldCheck class="size-5 text-brand" />
                  {{ t('Moyen de paiement') }}
                </h2>
                <p class="text-xs text-muted mt-0.5">
                  {{ t('Sélectionnez votre mode de règlement préféré.') }}
                </p>
              </div>
              <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800 border border-emerald-200">
                <Lock class="size-3" />
                {{ t('100% Sécurisé') }}
              </span>
            </div>

            <div class="mt-5 space-y-3.5">
              <!-- Option 1 : Mobile Money & Cartes Bancaires -->
              <div
                @click="payChoice = 'cinetpay'"
                :class="[
                  'group relative flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border p-4 sm:p-5 transition-all duration-200 cursor-pointer',
                  effectivePayWith === 'cinetpay'
                    ? 'border-brand ring-2 ring-brand/30 bg-gradient-to-r from-emerald-50/70 via-white to-teal-50/30 shadow-md'
                    : 'border-line bg-white hover:border-slate-300 hover:bg-slate-50/40 shadow-sm'
                ]"
              >
                <div class="flex items-start sm:items-center gap-4 min-w-0 flex-1">
                  <!-- Radio custom indicator -->
                  <div class="mt-0.5 sm:mt-0 flex shrink-0 items-center justify-center">
                    <div
                      :class="[
                        'size-5 rounded-full border-2 flex items-center justify-center transition',
                        effectivePayWith === 'cinetpay' ? 'border-brand bg-brand text-white' : 'border-slate-300 bg-white group-hover:border-slate-400'
                      ]"
                    >
                      <span v-if="effectivePayWith === 'cinetpay'" class="size-2 rounded-full bg-white" />
                    </div>
                  </div>

                  <!-- Icon badge -->
                  <div class="size-12 shrink-0 rounded-2xl bg-gradient-to-br from-amber-500 via-orange-500 to-rose-500 p-0.5 shadow-sm flex items-center justify-center text-white">
                    <div class="size-full rounded-[14px] bg-white/10 backdrop-blur-sm flex items-center justify-center">
                      <Smartphone class="size-6 text-white" />
                    </div>
                  </div>

                  <!-- Text details -->
                  <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="text-base font-extrabold text-ink">{{ t('Mobile Money ou Carte bancaire') }}</span>
                      <span class="inline-flex items-center rounded-full bg-orange-50 px-2 py-0.5 text-[10px] font-bold text-orange-700 border border-orange-200">
                        {{ t('Recommandé en Afrique') }}
                      </span>
                    </div>
                    <p class="text-xs text-muted leading-relaxed">
                      {{ t('Orange Money, Wave, MTN, Moov, Carte Visa ou Mastercard') }}
                    </p>

                    <!-- Badges des opérateurs -->
                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                      <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                        <span class="size-1.5 rounded-full bg-orange-500" /> Orange
                      </span>
                      <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                        <span class="size-1.5 rounded-full bg-sky-500" /> Wave
                      </span>
                      <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                        <span class="size-1.5 rounded-full bg-yellow-500" /> MTN
                      </span>
                      <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                        <span class="size-1.5 rounded-full bg-blue-500" /> Moov
                      </span>
                      <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                        <CreditCard class="size-3 text-slate-500" /> Visa / Mastercard
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Option 2 : Crypto-monnaies (Cryptomus) -->
              <div
                @click="payChoice = 'cryptomus'"
                :class="[
                  'group relative flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border p-4 sm:p-5 transition-all duration-200 cursor-pointer',
                  effectivePayWith === 'cryptomus'
                    ? 'border-brand ring-2 ring-brand/30 bg-gradient-to-r from-emerald-50/70 via-white to-purple-50/30 shadow-md'
                    : 'border-line bg-white hover:border-slate-300 hover:bg-slate-50/40 shadow-sm'
                ]"
              >
                <div class="flex items-start sm:items-center gap-4 min-w-0 flex-1">
                  <!-- Radio custom indicator -->
                  <div class="mt-0.5 sm:mt-0 flex shrink-0 items-center justify-center">
                    <div
                      :class="[
                        'size-5 rounded-full border-2 flex items-center justify-center transition',
                        effectivePayWith === 'cryptomus' ? 'border-brand bg-brand text-white' : 'border-slate-300 bg-white group-hover:border-slate-400'
                      ]"
                    >
                      <span v-if="effectivePayWith === 'cryptomus'" class="size-2 rounded-full bg-white" />
                    </div>
                  </div>

                  <!-- Icon badge -->
                  <div class="size-12 shrink-0 rounded-2xl bg-gradient-to-br from-indigo-500 via-purple-600 to-pink-500 p-0.5 shadow-sm flex items-center justify-center text-white">
                    <div class="size-full rounded-[14px] bg-white/10 backdrop-blur-sm flex items-center justify-center">
                      <Coins class="size-6 text-white" />
                    </div>
                  </div>

                  <!-- Text details -->
                  <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="text-base font-extrabold text-ink">{{ t('Crypto-monnaies') }}</span>
                      <span class="inline-flex items-center gap-1 rounded-full bg-purple-50 px-2 py-0.5 text-[10px] font-bold text-purple-700 border border-purple-200">
                        <Zap class="size-2.5 text-purple-600" />
                        {{ t('Cryptomus · Sans frais bancaires') }}
                      </span>
                    </div>
                    <p class="text-xs text-muted leading-relaxed">
                      {{ t('Paiement mondial instantané sur réseaux rapides à très faibles frais.') }}
                    </p>

                    <!-- Badges des cryptos -->
                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                      <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-800 border border-emerald-200">
                        USDT (TRC20 / BEP20)
                      </span>
                      <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                        Bitcoin
                      </span>
                      <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                        Solana
                      </span>
                      <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                        TON
                      </span>
                      <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                        Ethereum
                      </span>
                      <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                        Litecoin
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Option 3 : Mon solde client (Wallet) -->
              <div
                @click="canUseBalance && (payChoice = 'balance')"
                :class="[
                  'group relative flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border p-4 sm:p-5 transition-all duration-200',
                  !canUseBalance
                    ? 'cursor-not-allowed bg-slate-50/70 border-slate-200 opacity-65'
                    : effectivePayWith === 'balance'
                      ? 'cursor-pointer border-brand ring-2 ring-brand/30 bg-gradient-to-r from-teal-50/70 via-white to-emerald-50/40 shadow-md'
                      : 'cursor-pointer border-line bg-white hover:border-slate-300 hover:bg-slate-50/40 shadow-sm'
                ]"
              >
                <div class="flex items-start sm:items-center gap-4 min-w-0 flex-1">
                  <!-- Radio custom indicator -->
                  <div class="mt-0.5 sm:mt-0 flex shrink-0 items-center justify-center">
                    <div
                      :class="[
                        'size-5 rounded-full border-2 flex items-center justify-center transition',
                        !canUseBalance
                          ? 'border-slate-300 bg-slate-100'
                          : effectivePayWith === 'balance'
                            ? 'border-brand bg-brand text-white'
                            : 'border-slate-300 bg-white group-hover:border-slate-400'
                      ]"
                    >
                      <span v-if="effectivePayWith === 'balance'" class="size-2 rounded-full bg-white" />
                    </div>
                  </div>

                  <!-- Icon badge -->
                  <div
                    :class="[
                      'size-12 shrink-0 rounded-2xl p-0.5 shadow-sm flex items-center justify-center text-white',
                      canUseBalance
                        ? 'bg-gradient-to-br from-emerald-500 via-teal-500 to-[#0d5c4a]'
                        : 'bg-slate-300'
                    ]"
                  >
                    <div class="size-full rounded-[14px] bg-white/10 backdrop-blur-sm flex items-center justify-center">
                      <Wallet class="size-6 text-white" />
                    </div>
                  </div>

                  <!-- Text details -->
                  <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="text-base font-extrabold text-ink">{{ t('Mon solde client') }}</span>
                      <span
                        :class="[
                          'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-extrabold',
                          canUseBalance
                            ? 'bg-emerald-100 text-emerald-900 border border-emerald-300'
                            : 'bg-slate-200 text-slate-700'
                        ]"
                      >
                        <Sparkles v-if="canUseBalance" class="size-3 text-emerald-600" />
                        {{ formatXof(balanceXof) }} {{ t('disponible') }}
                      </span>
                    </div>

                    <p class="text-xs text-muted leading-relaxed">
                      <template v-if="canUseBalance">
                        <span class="font-semibold text-emerald-800">{{ t('Validation immédiate en 1 clic sans redirection.') }}</span>
                        {{ t(' Le montant de la commande sera automatiquement déduit de votre solde.') }}
                      </template>
                      <template v-else>
                        <span class="text-rose-600 font-semibold">{{ t('Solde insuffisant pour cette commande.') }}</span>
                        {{ t(' Rechargez votre solde depuis votre espace ou choisissez Mobile Money / Crypto.') }}
                      </template>
                    </p>
                  </div>
                </div>

                <!-- Tag d'état rapide -->
                <div v-if="canUseBalance" class="hidden sm:flex shrink-0">
                  <span class="inline-flex items-center gap-1 text-xs font-bold text-brand bg-brand/10 rounded-full px-3 py-1">
                    <Zap class="size-3.5" />
                    {{ t('Instantané') }}
                  </span>
                </div>
              </div>
            </div>
          </section>
        </div>

        <!-- Sidebar Récapitulatif -->
        <aside class="card space-y-4 p-5 shadow-pop lg:sticky lg:top-24">
          <div class="flex items-center gap-3.5">
            <BrandLogo :name="product.name" :title="product.title" size="md" />
            <div class="min-w-0">
              <p class="h3 leading-snug">{{ product.title }}</p>
              <p class="mt-0.5 text-xs text-muted">
                {{ product.durationLabel }} · {{ product.accessLabel }} · Quantité {{ quantity }}
              </p>
            </div>
          </div>

          <div class="flex items-baseline justify-between border-t border-line pt-4">
            <span class="text-sm font-semibold text-ink">{{ t('Total') }}</span>
            <div class="text-right">
              <span class="font-display text-3xl font-extrabold tracking-tight text-ink">
                {{ formatPrice(totalXof) }}
              </span>
              <p v-if="currency === 'USD'" class="text-xs text-muted mt-0.5">
                (Paiement en FCFA : {{ (totalXof || 0).toLocaleString('fr-FR') }} FCFA)
              </p>
            </div>
          </div>

          <div v-if="form.errors.error || form.errors.expectedTotalXof || $page.props.flash?.error" class="alert alert-error text-xs">
            {{ form.errors.error || form.errors.expectedTotalXof || $page.props.flash?.error }}
          </div>

          <button
            type="submit"
            :disabled="form.processing"
            class="btn btn-primary btn-block min-h-12 text-base shadow-glow cursor-pointer"
          >
            <span v-if="form.processing">{{ t('Vérification de l\'offre…') }}</span>
            <span v-else class="flex items-center justify-center gap-2">
              {{ t('Passer au paiement') }}
              <ArrowRight class="size-4" aria-hidden="true" />
            </span>
          </button>

          <p class="text-center text-xs leading-relaxed text-muted">
            En validant votre commande, vous acceptez nos&nbsp;
            <Link href="/conditions" target="_blank" class="underline hover:text-ink">
              conditions de vente
            </Link>
            .
          </p>

          <p class="flex items-start gap-2 text-xs leading-relaxed text-muted">
            <Lock class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
            Le prix est celui enregistré à la validation. Vous choisirez votre moyen de paiement sur la page sécurisée du prestataire.
          </p>
        </aside>
      </form>
    </div>
  </SiteLayout>
</template>
