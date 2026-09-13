<script setup lang="ts" generic="T extends Record<string, any>">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ChevronLeft, ChevronRight, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Column {
    key: string;
    label: string;
    class?: string;
    align?: 'left' | 'center' | 'right';
}

const props = withDefaults(
    defineProps<{
        columns: Column[];
        data: T[];
        searchable?: boolean;
        searchPlaceholder?: string;
        searchKeys?: string[];
        pageSize?: number;
        title?: string;
        description?: string;
        maxHeight?: string;
        stickyHeader?: boolean;
    }>(),
    {
        searchable: true,
        searchPlaceholder: 'Search records...',
        pageSize: 10,
        stickyHeader: false,
    },
);

const searchQuery = ref('');
const currentPage = ref(1);

const filteredData = computed(() => {
    if (!props.searchQuery && !searchQuery.value.trim()) {
        return props.data;
    }
    const q = searchQuery.value.toLowerCase().trim();
    return props.data.filter((item) => {
        if (props.searchKeys && props.searchKeys.length > 0) {
            return props.searchKeys.some((k) => {
                const val = item[k];
                return val !== undefined && val !== null && String(val).toLowerCase().includes(q);
            });
        }
        return Object.values(item).some((val) => {
            return val !== undefined && val !== null && String(val).toLowerCase().includes(q);
        });
    });
});

const totalPages = computed(() => {
    return Math.ceil(filteredData.value.length / props.pageSize) || 1;
});

const paginatedData = computed(() => {
    const start = (currentPage.value - 1) * props.pageSize;
    return filteredData.value.slice(start, start + props.pageSize);
});

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};
</script>

<template>
    <div class="space-y-3 sm:space-y-4">
        <!-- Header & Search Toolbar -->
        <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
            <div v-if="title || description">
                <h3 v-if="title" class="text-sm sm:text-base font-semibold text-foreground">{{ title }}</h3>
                <p v-if="description" class="text-[11px] sm:text-xs text-muted-foreground">{{ description }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <div v-if="searchable" class="relative w-full sm:w-auto sm:min-w-[220px]">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        :placeholder="searchPlaceholder"
                        class="h-9 pl-8 text-xs bg-background w-full"
                        @input="currentPage = 1"
                    />
                </div>
                <slot name="filters" />
                <slot name="actions" />
            </div>
        </div>

        <!-- Table Container with Horizontal & Optional Vertical Scroll -->
        <div class="rounded-xl border border-border/80 bg-card overflow-hidden shadow-sm">
            <div :class="['overflow-x-auto scrollbar-thin', maxHeight ? `${maxHeight} overflow-y-auto` : '']">
                <table class="w-full text-left text-xs whitespace-nowrap sm:whitespace-normal">
                    <thead :class="['border-b border-border/70 bg-muted/40 text-muted-foreground', (stickyHeader || maxHeight) ? 'sticky top-0 z-10 bg-muted/95 backdrop-blur-xs shadow-xs' : '']">
                        <tr>
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                :class="[
                                    'px-3 sm:px-4 py-3 font-semibold uppercase tracking-wider text-[11px]',
                                    col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left',
                                    col.class,
                                ]"
                            >
                                {{ col.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="(row, idx) in paginatedData"
                            :key="idx"
                            class="transition-colors hover:bg-muted/30"
                        >
                            <td
                                v-for="col in columns"
                                :key="col.key"
                                :class="[
                                    'px-3 sm:px-4 py-3',
                                    col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left',
                                    col.class,
                                ]"
                            >
                                <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                                    {{ row[col.key] }}
                                </slot>
                            </td>
                        </tr>

                        <tr v-if="paginatedData.length === 0">
                            <td :colspan="columns.length" class="px-4 py-12 text-center text-muted-foreground">
                                <slot name="empty">
                                    <div class="flex flex-col items-center justify-center gap-1">
                                        <p class="text-sm font-medium">No matching records found</p>
                                        <p class="text-xs text-muted-foreground">Try adjusting your search query or filters</p>
                                    </div>
                                </slot>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div
                v-if="filteredData.length > pageSize"
                class="flex flex-col sm:flex-row items-center justify-between border-t border-border/70 bg-muted/20 px-3 sm:px-4 py-2.5 text-xs text-muted-foreground gap-2 sm:gap-0"
            >
                <div class="text-[11px] sm:text-xs">
                    Showing <span class="font-medium text-foreground">{{ (currentPage - 1) * pageSize + 1 }}</span> to
                    <span class="font-medium text-foreground">{{ Math.min(currentPage * pageSize, filteredData.length) }}</span> of
                    <span class="font-medium text-foreground">{{ filteredData.length }}</span> entries
                </div>

                <div class="flex items-center gap-1.5">
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-7 w-7 p-0"
                        :disabled="currentPage === 1"
                        @click="goToPage(currentPage - 1)"
                    >
                        <ChevronLeft class="size-3.5" />
                    </Button>
                    <span class="px-2 text-xs font-medium text-foreground">Page {{ currentPage }} of {{ totalPages }}</span>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-7 w-7 p-0"
                        :disabled="currentPage === totalPages"
                        @click="goToPage(currentPage + 1)"
                    >
                        <ChevronRight class="size-3.5" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
