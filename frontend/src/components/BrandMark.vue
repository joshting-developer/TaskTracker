<script setup>
import markUrl from '../../public-assets/brand/pavenro-mark-source.svg';
</script>

<template>
  <img class="brand-mark-image" :src="markUrl" alt="" aria-hidden="true" width="28" height="28" />
</template>

<style scoped>
.brand-mark-image { display: block; flex-shrink: 0; }
</style>
