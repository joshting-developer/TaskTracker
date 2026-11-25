<script setup>
import { ref } from 'vue';
import { t } from '../i18n/index.js';
const props = defineProps({ columns: Number, compact: Boolean, height: Number, editing: Boolean });
const emit = defineEmits(['resize']);
const frame = ref(null);
let drag;
function start(event) {
  if (!props.editing) return;
  const grid = frame.value.closest('.dashboard-grid');
  drag = { x: event.clientX, y: event.clientY, columns: props.columns, height: props.height, unit: (grid.clientWidth + 20) / 12 };
  event.currentTarget.setPointerCapture(event.pointerId);
}
function move(event) {
  if (!drag) return;
  emit('resize', { columns: Math.max(3, Math.min(12, drag.columns + Math.round((event.clientX - drag.x) / drag.unit))), height: Math.max(180, Math.min(1000, Math.round((drag.height + event.clientY - drag.y) / 20) * 20)) });
}
function key(event) {
  const steps = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -20], ArrowDown: [0, 20] };
  if (!steps[event.key]) return;
  event.preventDefault();
  const [w, h] = steps[event.key];
  emit('resize', { columns: Math.max(3, Math.min(12, props.columns + w)), height: Math.max(180, Math.min(1000, props.height + h)) });
}
</script>
<template>
  <div ref="frame" class="widget-frame" :style="{ '--widget-height': `${compact && !editing ? Math.min(height, 190) : height}px` }">
    <slot />
    <button v-if="editing" class="resize-handle" type="button" :aria-label="t('調整小工具大小')" :aria-description="`${columns} / 12, ${height}px`" :title="t('拖曳或使用方向鍵調整大小')" @pointerdown="start" @pointermove="move" @pointerup="drag = null" @pointercancel="drag = null" @lostpointercapture="drag = null" @keydown="key">↘</button>
  </div>
</template>
<style scoped>
.widget-frame { height: var(--widget-height); container-type: inline-size; position: relative; min-width: 0; }
.resize-handle { position: absolute; right: 0; bottom: 0; width: 44px; height: 44px; border: 1px solid #d9cff0; background: #f5f1fc; color: #5844a0; border-radius: 8px 0; cursor: nwse-resize; touch-action: none; }
@media (max-width: 700px) { .widget-frame { height: min(var(--widget-height), 440px); } }
</style>
