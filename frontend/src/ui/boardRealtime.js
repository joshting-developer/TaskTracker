import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Pusher from 'pusher-js';
import { api } from '../api/index.js';

// 訂閱只存在目前看板範圍；訊號只觸發既有API刷新，不攜帶業務資料。
export function useBoardRealtime({ active, userId, workspaceId, projectId, refresh }) {
  const state = ref('idle');
  const key = computed(() => active.value && userId.value && workspaceId.value && projectId.value ? `${userId.value}:${workspaceId.value}:${projectId.value}` : '');
  let client, generation = 0, debounce, retry, running = false, pending = false;
  function stop() {
    generation++; clearTimeout(debounce); clearTimeout(retry); pending = false;
    client?.disconnect(); client = null; state.value = 'idle';
  }
  async function flush(current) {
    if (current !== generation || !key.value) return;
    if (running) { pending = true; return; }
    running = true;
    try { await refresh(); }
    finally {
      running = false;
      if (pending && key.value) { pending = false; schedule(generation); }
    }
  }
  function schedule(current) { clearTimeout(debounce); debounce = setTimeout(() => flush(current), 200); }
  async function connect() {
    stop(); if (!key.value) return;
    const current = generation;
    state.value = 'connecting';
    try {
      const config = await api.realtimeConfig();
      if (current !== generation) return;
      if (!config.enabled) { state.value = 'polling'; return; }
      const channelName = `private-workspaces.${workspaceId.value}.projects.${projectId.value}`;
      client = new Pusher(config.key, {
        cluster: 'mt1', wsHost: location.hostname, wsPort: Number(location.port || 80), wssPort: Number(location.port || 443),
        forceTLS: location.protocol === 'https:', enabledTransports: ['ws', 'wss'], wsPath: config.path, disableStats: true,
        channelAuthorization: { customHandler: async ({ socketId, channelName }, callback) => {
          try { const auth = await api.authorizeRealtime({ socket_id: socketId, channel_name: channelName }); if (current === generation) callback(null, auth); }
          catch (error) { if (current === generation) callback(error, null); }
        } },
      });
      client.connection.bind('state_change', ({ current: status }) => {
        if (current === generation && status !== 'connected') state.value = status === 'connecting' ? 'connecting' : 'polling';
      });
      const channel = client.subscribe(channelName);
      channel.bind('pusher:subscription_succeeded', () => { if (current === generation) { state.value = 'live'; schedule(current); } });
      channel.bind('pusher:subscription_error', () => {
        if (current !== generation) return;
        state.value = 'polling'; schedule(current); clearTimeout(retry); retry = setTimeout(connect, 15000);
      });
      channel.bind('project.changed', () => { if (current === generation) schedule(current); });
    } catch {
      if (current === generation) { state.value = 'polling'; retry = setTimeout(connect, 15000); }
    }
  }
  watch(key, connect, { immediate: true });
  onBeforeUnmount(stop);
  return state;
}
