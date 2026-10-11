<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const isTenantLocked = computed(() => {
    return Boolean(page.props?.tenant || page.props?.isSubdomain);
});
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <!-- If in a tenant portal, the logo is locked to prevent escaping to the central welcome page -->
                    <div v-if="isTenantLocked" class="flex cursor-default select-none flex-col items-center gap-2 font-medium">
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-primary text-base font-black text-primary-foreground shadow-sm"
                        >
                            MDE
                        </div>
                        <span class="sr-only">{{ title }}</span>
                    </div>
                    <Link v-else :href="route('home')" class="group flex flex-col items-center gap-2 font-medium">
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-primary text-base font-black text-primary-foreground shadow-sm transition-transform group-hover:scale-105"
                        >
                            MDE
                        </div>
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">{{ description }}</p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
