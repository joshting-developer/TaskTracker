import { defineAsyncComponent, defineComponent, h, shallowRef } from 'vue';

// 讓Vue原生async wrapper繼續轉送ref、props與事件，草稿與saving仍由實際功能持有。
// 失敗時等使用者重試，不背景重試、重新整理或重建已載入的其他功能。
export function lazyFeature(loader) {
  const failure = shallowRef(null);
  const loadingComponent = defineComponent({
    setup() {
      function reload() {
        if (window.confirm('重新載入會放棄未儲存的內容，確定嗎？')) location.reload();
      }
      return () => failure.value
        ? h('section', { class: 'feature-load-state', role: 'alert' }, [
          h('p', '無法載入此功能'),
          h('button', { type: 'button', class: 'button secondary', onClick: failure.value.retry }, '重試載入'),
          h('button', { type: 'button', class: 'text-button', onClick: reload }, '重新載入頁面'),
        ])
        : h('p', { class: 'feature-load-state', role: 'status' }, '載入中…');
    },
  });
  return defineAsyncComponent({
    loader: () => { failure.value = null; return loader(); },
    loadingComponent,
    delay: 150,
    onError(_error, retry) { failure.value = { retry }; },
  });
}
