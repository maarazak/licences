<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Send, UserRound, Shield } from 'lucide-vue-next';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import { TICKET_STATUS_LABELS } from '@/Types/enums';
import { useI18n } from '@/Utils/useI18n';

const props = defineProps<{
  ticket: {
    id: string;
    subject: string;
    status: string;
    created_at: string;
    messages: Array<{
      id: string;
      message: string;
      is_staff: boolean;
      created_at: string;
    }>;
  };
}>();

const { t, lang } = useI18n();

const replyForm = useForm({
  message: '',
});

const submitReply = () => {
  replyForm.post(`/compte/assistance/${props.ticket.id}/repondre`, {
    preserveScroll: true,
    onSuccess: () => {
      replyForm.reset();
    },
  });
};
</script>

<template>
  <AccountLayout>
    <Head :title="`${t('Ticket : {subject}', { subject: ticket.subject })} — ${t('Assistance client')}`" />

    <div class="space-y-6 max-w-3xl">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <Link
          href="/compte/assistance"
          class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-slate-900 transition"
        >
          <ArrowLeft class="size-4" />
          {{ t('Tous les tickets') }}
        </Link>

        <span
          :class="[
            'badge text-xs px-3 py-1',
            ticket.status === 'open' ? 'badge-warning' : '',
            ticket.status === 'answered' ? 'badge-success' : '',
            ticket.status === 'closed' ? 'badge-neutral' : '',
          ]"
        >
          {{ t(TICKET_STATUS_LABELS[ticket.status as keyof typeof TICKET_STATUS_LABELS] || ticket.status) }}
        </span>
      </div>

      <div class="card p-6 shadow-card space-y-4">
        <h1 class="h2 text-xl font-bold">{{ ticket.subject }}</h1>
        <p class="text-xs text-slate-400">
          {{ t('Ticket ouvert le') }} {{ new Date(ticket.created_at).toLocaleDateString(lang === 'en' ? 'en-US' : 'fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
        </p>
      </div>

      <!-- Messages Thread -->
      <div class="space-y-4">
        <div
          v-for="msg in ticket.messages"
          :key="msg.id"
          :class="[
            'card p-5 space-y-2.5 transition',
            msg.is_staff
              ? 'bg-teal-50/60 border-teal-200/80 shadow-sm ml-4 sm:ml-8'
              : 'bg-white border-slate-200/80 shadow-card mr-4 sm:mr-8',
          ]"
        >
          <div class="flex items-center justify-between text-xs">
            <div class="flex items-center gap-2 font-bold">
              <Shield v-if="msg.is_staff" class="size-4 text-[#0d5c4a]" />
              <UserRound v-else class="size-4 text-slate-500" />
              <span :class="msg.is_staff ? 'text-[#0d5c4a]' : 'text-slate-800'">
                {{ msg.is_staff ? t('Équipe Support Licences') : t('Vous') }}
              </span>
            </div>
            <span class="text-slate-400">
              {{ new Date(msg.created_at).toLocaleDateString(lang === 'en' ? 'en-US' : 'fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
            </span>
          </div>

          <div class="prose-plain text-slate-700 text-sm whitespace-pre-wrap leading-relaxed">
            {{ msg.message }}
          </div>
        </div>
      </div>

      <!-- Reply Box -->
      <div v-if="ticket.status !== 'closed'" class="card p-6 shadow-card">
        <form @submit.prevent="submitReply" class="space-y-4">
          <h3 class="h3 text-sm">{{ t('Ajouter une réponse') }}</h3>
          <textarea
            v-model="replyForm.message"
            required
            rows="4"
            :placeholder="t('Écrivez votre message...')"
            class="input text-sm py-3"
          />

          <div class="flex justify-end">
            <button
              type="submit"
              :disabled="replyForm.processing || !replyForm.message.trim()"
              class="btn btn-primary btn-sm px-5 cursor-pointer"
            >
              <Send class="size-3.5" />
              <span>{{ t('Envoyer ma réponse') }}</span>
            </button>
          </div>
        </form>
      </div>
      <div v-else class="alert alert-info text-center text-xs">
        {{ t("Ce ticket a été clôturé. Si vous avez un nouveau problème, ouvrez une nouvelle demande d'aide.") }}
      </div>
    </div>
  </AccountLayout>
</template>
