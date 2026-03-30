<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { MagnifyingGlassIcon, PlusIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    articles: Array,
    categories: Object,
    filters: Object,
    isAdmin: Boolean,
});

const search = ref(props.filters?.search || '');
const category = ref(props.filters?.category || '');

let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});
watch(category, () => applyFilters());

function applyFilters() {
    router.get('/admin/help', {
        search: search.value || undefined,
        category: category.value || undefined,
    }, { preserveState: true, replace: true });
}

const groupedArticles = computed(() => {
    if (category.value) return { [category.value]: props.articles };
    const groups = {};
    props.articles.forEach(a => {
        if (!groups[a.category]) groups[a.category] = [];
        groups[a.category].push(a);
    });
    return groups;
});

const categoryColors = {
    general: 'bg-gray-100 text-gray-700',
    sales: 'bg-blue-50 text-blue-700',
    operations: 'bg-orange-50 text-orange-700',
    models: 'bg-pink-50 text-pink-700',
    designers: 'bg-purple-50 text-purple-700',
    media: 'bg-cyan-50 text-cyan-700',
    volunteers: 'bg-green-50 text-green-700',
    accounting: 'bg-yellow-50 text-yellow-700',
    events: 'bg-indigo-50 text-indigo-700',
    tickets: 'bg-teal-50 text-teal-700',
    marketing: 'bg-red-50 text-red-700',
};
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <h2 class="text-lg font-semibold text-gray-900">Help Center</h2>
                <Link v-if="isAdmin" href="/admin/help/create"
                    class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors flex items-center gap-1.5">
                    <PlusIcon class="w-4 h-4" /> New Article
                </Link>
            </div>
        </template>

        <div class="max-w-5xl mx-auto">
            <!-- Search & Filters -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
                <div class="flex items-center gap-4">
                    <div class="flex-1 relative">
                        <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input v-model="search" type="text" placeholder="Search articles..."
                            class="w-full border border-gray-200 rounded-lg pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400" />
                    </div>
                    <select v-model="category" class="border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 bg-white">
                        <option value="">All categories</option>
                        <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
            </div>

            <!-- Articles grouped by category -->
            <div v-for="(articles, cat) in groupedArticles" :key="cat" class="mb-8">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">{{ categories[cat] || cat }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Link v-for="article in articles" :key="article.id"
                        :href="`/admin/help/${article.id}`"
                        class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md hover:border-gray-300 transition-all group">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0 group-hover:bg-black group-hover:text-white transition-colors">
                                <DocumentTextIcon class="w-5 h-5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-semibold text-gray-900 text-sm group-hover:text-black">{{ article.title }}</h4>
                                <p v-if="article.description" class="text-xs text-gray-500 mt-1 line-clamp-2">{{ article.description }}</p>
                                <div class="flex items-center gap-2 mt-2">
                                    <span :class="categoryColors[article.category] || 'bg-gray-100 text-gray-700'" class="text-[10px] font-medium px-2 py-0.5 rounded-full">
                                        {{ categories[article.category] || article.category }}
                                    </span>
                                    <span v-if="article.author" class="text-[10px] text-gray-400">by {{ article.author.first_name }}</span>
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="!articles.length" class="text-center py-16">
                <DocumentTextIcon class="w-12 h-12 text-gray-300 mx-auto" />
                <p class="mt-3 text-sm text-gray-500">No articles found</p>
                <Link v-if="isAdmin" href="/admin/help/create"
                    class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800">
                    <PlusIcon class="w-4 h-4" /> Create first article
                </Link>
            </div>
        </div>
    </AdminLayout>
</template>
