<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-shell">
      <div class="modal-header">
        <div class="modal-title-wrap">
          <div class="modal-title">{{ title }}</div>
          <div v-if="subtitle" class="modal-subtitle">{{ subtitle }}</div>
        </div>
        <button class="btn btn-light" type="button" @click="$emit('close')">Close</button>
      </div>

      <div class="modal-body">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: '',
  },
})

defineEmits(['close'])
</script>
