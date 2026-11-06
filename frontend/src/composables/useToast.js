import { onScopeDispose, readonly, shallowRef } from 'vue';

// 提示與計時器一起釋放，App不需要管理隱藏的timer。
export function useToast() {
  const message = shallowRef('');
  let timer;
  function notify(value) {
    message.value = value;
    clearTimeout(timer);
    timer = setTimeout(() => { message.value = ''; }, 3500);
  }
  onScopeDispose(() => clearTimeout(timer));
  return { toast: readonly(message), notify };
}
