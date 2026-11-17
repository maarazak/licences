import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { makeT, type TFn } from './i18n';
import { EN, EN_PATTERNS } from './i18n-en';
import { formatMoney, type Currency } from './money';

const tEn = makeT(EN, EN_PATTERNS);
const tFr = makeT({});

export function useI18n() {
  const page = usePage();
  const locale = computed(() => (page.props.locale as any) || { lang: 'fr', currency: 'XOF', usdRate: 669 });

  const lang = computed(() => locale.value.lang || 'fr');
  const currency = computed<Currency>(() => locale.value.currency || 'XOF');
  const usdRate = computed<number>(() => locale.value.usdRate || 669);

  const t: TFn = (key: string, vars?: any) => {
    return lang.value === 'en' ? tEn(key, vars) : tFr(key, vars);
  };

  const format = (amountXof: number) => {
    return formatMoney(amountXof, currency.value, usdRate.value, lang.value);
  };

  return {
    lang,
    currency,
    usdRate,
    t,
    format,
    formatPrice: format,
  };
}
