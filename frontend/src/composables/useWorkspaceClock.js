import { onMounted, onScopeDispose, readonly, shallowRef } from 'vue';
import { dateInTaipei } from '../domain/model.js';

// 台北日期與背景刷新共用一個計時器，切換空間／未登入時不發刷新。
export function useWorkspaceClock({ active, refresh }) {
  const today = shallowRef(dateInTaipei());
  let timer;
  onMounted(() => {
    timer = setInterval(() => {
      today.value = dateInTaipei();
      if (active.value) refresh();
    }, 30000);
  });
  onScopeDispose(() => clearInterval(timer));
  return readonly(today);
}
