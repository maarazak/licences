<script setup lang="ts">
withDefaults(
  defineProps<{
    size?: number;
    tone?: 'dark' | 'light';
  }>(),
  {
    size: 34,
    tone: 'dark',
  }
);
</script>

<template>
  <span class="flex items-center gap-2.5">
    <svg :width="size" :height="size" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
      <!-- Ticket vert aux encoches latérales -->
      <path
        d="M12 0H36A12 12 0 0 1 48 12V19.5A4.5 4.5 0 0 0 48 28.5V36A12 12 0 0 1 36 48H12A12 12 0 0 1 0 36V28.5A4.5 4.5 0 0 0 0 19.5V12A12 12 0 0 1 12 0Z"
        fill="#0d5c4a"
      />
      <!-- Lettre L blanche -->
      <path d="M16 12H23V29.5H33V36H16Z" fill="#ffffff" />
      <!-- Pastille orange -->
      <circle cx="29.5" cy="15.5" r="3.5" fill="#e8590c" />
    </svg>
    <span
      :class="[
        'font-display text-xl font-extrabold tracking-tight',
        tone === 'light' ? 'text-white' : 'text-ink',
      ]"
    >
      Licences
    </span>
  </span>
</template>
