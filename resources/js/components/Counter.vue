<script setup>
import { ref } from 'vue';
const props = defineProps({ start: { type: Number, default: 0 } });
const count = ref(props.start);
</script>

<template>
  <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-6">
    <span class="text-sm text-slate-500">Vue 3 island</span>
    <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500" @click="count--">−</button>
    <span class="min-w-8 text-center text-xl font-semibold tabular-nums">{{ count }}</span>
    <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500" @click="count++">+</button>
  </div>
</template>
