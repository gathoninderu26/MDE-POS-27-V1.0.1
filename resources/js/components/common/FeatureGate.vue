<script setup lang="ts">
import { useFeatures } from '@/composables/useFeatures';
import { computed } from 'vue';

const props = defineProps<{
    module?: string;
    feature?: string;
    inverse?: boolean;
}>();

const { hasModule, hasFeature } = useFeatures();

const isVisible = computed(() => {
    let allowed = true;
    if (props.module) {
        allowed = allowed && hasModule(props.module);
    }
    if (props.feature) {
        allowed = allowed && hasFeature(props.feature);
    }
    return props.inverse ? !allowed : allowed;
});
</script>

<template>
    <slot v-if="isVisible" />
    <slot v-else name="fallback" />
</template>
