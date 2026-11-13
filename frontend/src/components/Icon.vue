<script setup>
defineProps({ name: String, size: { type: Number, default: 20 } });
const paths = {
  settings: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M5 19l2-2M17 7l2-2',
  bell: 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9 M10 21h4',
  grid: 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
  list: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
  board: 'M3 4h18v16H3z M9 4v16M15 4v16M6 8v3M12 8v6M18 8v2',
  search: 'M21 21l-5-5 M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0',
  plus: 'M12 5v14M5 12h14',
  edit: 'M16 3l5 5M4 20l4-1L21 6a2 2 0 0 0-4-4L4 15z',
  close: 'M6 6l12 12M18 6L6 18',
  check: 'M5 12l4 4L19 6',
  chevron: 'M9 5l7 7-7 7',
  arrow: 'M7 17L17 7M7 7h10v10',
  arrowDown: 'M12 5v14M6 13l6 6 6-6',
  arrowUp: 'M12 19V5M6 11l6-6 6 6',
  folder: 'M3 7V4h6l3 3h9v13H3z',
  user: 'M20 21v-2a7 7 0 0 0-14 0v2 M17 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
  clock: 'M12 8v5l3 2 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
  filter: 'M4 7h16M7 12h10M10 17h4',
  menu: 'M4 6h16M4 12h16M4 18h16',
  sun: 'M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1 1M18 18l1 1M5 19l1-1M18 6l1-1 M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
  message: 'M21 15a3 3 0 0 1-3 3H8l-5 3V6a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3z',
  refresh: 'M20 6v5h-5M4 18v-5h5 M5 8a8 8 0 0 1 13-3l2 3M4 16l2 3a8 8 0 0 0 13-3',
  diamond: 'M12 3l9 9-9 9-9-9z',
  flag: 'M5 21V3h14l-3 5 3 5H5',
  link: 'M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-2 2 M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l2-2',
};
</script>

<template>
  <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="paths[name] || paths.diamond" /></svg>
</template>
