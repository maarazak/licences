<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Send, LifeBuoy } from 'lucide-vue-next';
import AccountLayout from '@/Layouts/AccountLayout.vue';

const props = defineProps<{
  order?: {
    id: string;
    reference: string;
    product_name: string;
  } | null;
}>();

const form = useForm({
  subject: props.order ? `Assistance commande ${props.order.reference} (${props.order.product_name})` : '',
  message: '',
  order_id: props.order?.id || null,
});

const submitTicket = () => {
  form.post('/compte/assistance');
};
</script>

<template>
  <AccountLayout>
    <Head title="Créer un ticket — Assistance client" />

    <div class="space-y-6 max-w-2xl">
      <div class="flex items-center gap-3">
        <Link
          href="/compte/assistance"
          class="p-2 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100 transition"
        >
          <ArrowLeft class="size-4" />
        </Link>
        <div>
          <h1 class="h1 text-2xl">Ouvrir un ticket d'aide</h1>
          <p class="text-xs text-slate-500 mt-0.5">
            Détaillez votre problème ou votre question, nous vous répondrons dans les plus brefs délais.
          </p>
        </div>
      </div>

      <div class="card p-6 sm:p-8 shadow-card">
        <form @submit.prevent="submitTicket" class="space-y-5">
          <div v-if="order" class="p-3.5 bg-teal-50 border border-teal-200/80 rounded-xl text-xs text-teal-900">
            Demande liée à la commande <strong>{{ order.reference }}</strong> ({{ order.product_name }})
          </div>

          <div>
            <label class="label text-xs">Objet de votre demande <span class="text-red-500">*</span></label>
            <input
              v-model="form.subject"
              type="text"
              required
              placeholder="Ex. Problème de connexion, clé non reconnue..."
              class="input text-sm"
            />
          </div>

          <div>
            <label class="label text-xs">Votre message détaillé <span class="text-red-500">*</span></label>
            <textarea
              v-model="form.message"
              required
              rows="6"
              placeholder="Expliquez ce qui se passe, le message d'erreur éventuel..."
              class="input text-sm py-3 leading-relaxed"
            />
          </div>

          <div class="pt-2 flex items-center justify-end gap-3 border-t border-slate-100">
            <Link href="/compte/assistance" class="btn btn-soft btn-sm">
              Annuler
            </Link>
            <button
              type="submit"
              :disabled="form.processing"
              class="btn btn-primary btn-sm min-h-10 px-5"
            >
              <Send class="size-3.5" />
              <span>Envoyer le ticket</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AccountLayout>
</template>
