import { onBeforeUnmount, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Re-fetch the given props every 30 seconds while `isActive()` is true and
 * the tab is visible -- used for live order tracking.
 */
export function useLivePoll(isActive, only, interval = 30000) {
    let timer = null;

    function tick() {
        if (document.hidden || !isActive()) return;
        router.reload({ only, preserveScroll: true, preserveState: true });
    }

    onMounted(() => { timer = setInterval(tick, interval); });
    onBeforeUnmount(() => clearInterval(timer));
}
