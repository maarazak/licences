<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, Lock, Minus, Plus } from 'lucide-vue-next';
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
const payChoice = ref<'gateway' | 'balance'>('gateway');

const currentCountry = computed(() => getCountry(selectedCountry.value));

const totalXof = computed(() => props.product.unitPriceXof * quantity.value);
const canUseBalance = computed(() => props.balanceXof >= totalXof.value);
const effectivePayWith = computed(() => (canUseBalance.value ? payChoice.value : 'gateway'));

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
          <section class="card p-5 sm:p-6">
            <h2 class="h3 text-lg">Moyen de paiement</h2>
            <div class="mt-3 space-y-2.5">
              <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line p-3.5 has-[:checked]:border-brand has-[:checked]:bg-brand-soft/50 transition">
                <input
                  type="radio"
                  name="payWith"
                  value="gateway"
                  :checked="effectivePayWith === 'gateway'"
                  @change="payChoice = 'gateway'"
                  class="mt-1 size-4 accent-brand"
                />
                <span class="text-sm">
                  <span class="font-semibold text-ink">Carte bancaire ou Mobile Money (CinetPay)</span>
                  <span class="block text-muted">Visa, Mastercard, Orange Money, Moov Money, MTN, Wave...</span>
                </span>
              </label>

              <label
                :class="[
                  'flex items-start gap-3 rounded-xl border border-line p-3.5 transition',
                  canUseBalance ? 'cursor-pointer has-[:checked]:border-brand has-[:checked]:bg-brand-soft/50' : 'cursor-not-allowed bg-canvas-subtle/50 opacity-60'
                ]"
              >
                <input
                  type="radio"
                  name="payWith"
                  value="balance"
                  :checked="effectivePayWith === 'balance'"
                  @change="payChoice = 'balance'"
                  :disabled="!canUseBalance"
                  class="mt-1 size-4 accent-brand disabled:cursor-not-allowed"
                />
                <span class="text-sm">
                  <span class="font-semibold text-ink">
                    Mon solde ({{ formatXof(balanceXof) }})
                  </span>
                  <span class="block text-muted">
                    {{ canUseBalance ? 'Le solde paie la commande entière.' : 'Solde insuffisant pour cette commande.' }}
                  </span>
                </span>
              </label>
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
