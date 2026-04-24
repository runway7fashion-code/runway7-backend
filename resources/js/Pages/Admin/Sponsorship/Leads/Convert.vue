<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { StarIcon, ExclamationTriangleIcon, XMarkIcon, DocumentTextIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    lead: Object,
    packages: Array,
    countries: Array,
});

const primaryEmail = props.lead.emails?.find(e => e.is_primary)?.email || '';

const form = useForm({
    // Company
    company_name: props.lead.company?.name || '',
    company_website: props.lead.company?.website || props.lead.website_url || '',
    company_instagram: props.lead.company?.instagram || props.lead.instagram || '',
    company_industry: props.lead.company?.industry || '',
    company_country: props.lead.company?.country || '',
    company_notes: props.lead.company?.notes || '',
    company_logo: props.lead.company?.logo || '',
    // Email
    email: primaryEmail,
    email_confirmed: false,
    // Registration
    event_id: props.lead.events?.[0]?.id || '',
    package_id: '',
    agreed_price: 0,
    downpayment: 0,
    installments_count: 1,
    notes: '',
    // Documents
    documents: [],
});

const selectedPackage = computed(() => props.packages.find(p => p.id === Number(form.package_id)));

function onPackageChange() {
    if (selectedPackage.value) {
        form.agreed_price = Number(selectedPackage.value.price) || 0;
    }
}

function addDocument(e) {
    const files = Array.from(e.target.files || []);
    form.documents = [...form.documents, ...files];
    e.target.value = '';
}
function removeDocument(i) {
    form.documents.splice(i, 1);
}

function submit() {
    form.post(`/admin/sponsorship/leads/${props.lead.id}/convert`, {
        forceFormData: true,
    });
}

function formatFileSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}
</script>

<template>
    <AdminLayout>
        <template #header>
            <div class="flex items-center space-x-2 text-sm">
                <Link href="/admin/sponsorship/leads" class="text-gray-400 hover:text-gray-600">Leads</Link>
                <span class="text-gray-300">/</span>
                <Link :href="`/admin/sponsorship/leads/${lead.id}`" class="text-gray-400 hover:text-gray-600">{{ lead.first_name }} {{ lead.last_name }}</Link>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-medium">Convert to Sponsor</span>
            </div>
        </template>

        <div class="max-w-3xl">
            <div class="flex items-center gap-2 mb-2">
                <StarIcon class="w-6 h-6 text-[#D4AF37]" />
                <h3 class="text-2xl font-bold text-gray-900">Close Contract & Convert to Sponsor</h3>
            </div>
            <p class="text-sm text-gray-500 mb-6">
                This will mark this lead as the contract winner, close all other leads from <strong>{{ lead.company?.name }}</strong>,
                and create a new sponsor user with password <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs">runway7</code>.
            </p>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- 1. Company data -->
                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                    <h4 class="font-semibold text-gray-900 pb-2 border-b-2 border-[#D4AF37]">① Company data</h4>

                    <div>
                        <label class="label">Company name *</label>
                        <input v-model="form.company_name" type="text" class="input" />
                        <p v-if="form.errors.company_name" class="err">{{ form.errors.company_name }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Industry</label>
                            <input v-model="form.company_industry" type="text" class="input" />
                        </div>
                        <div>
                            <label class="label">Country</label>
                            <select v-model="form.company_country" class="input bg-white">
                                <option value="">Select country...</option>
                                <option v-for="c in countries" :key="c.code" :value="c.name">{{ c.flag }} {{ c.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Website</label>
                            <input v-model="form.company_website" type="url" class="input" placeholder="https://" />
                        </div>
                        <div>
                            <label class="label">Instagram</label>
                            <input v-model="form.company_instagram" type="text" class="input" placeholder="@brand" />
                        </div>
                    </div>

                    <div>
                        <label class="label">Logo URL</label>
                        <input v-model="form.company_logo" type="text" class="input" placeholder="https://..." />
                    </div>

                    <div>
                        <label class="label">Company notes</label>
                        <textarea v-model="form.company_notes" rows="3" class="input resize-none"></textarea>
                    </div>
                </div>

                <!-- 2. Email confirmation -->
                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-3">
                    <h4 class="font-semibold text-gray-900 pb-2 border-b-2 border-[#D4AF37]">② Confirm primary email</h4>
                    <p class="text-sm text-gray-500">This email will become the sponsor's login for the mobile app.</p>

                    <div>
                        <label class="label">Primary email *</label>
                        <input v-model="form.email" type="email" class="input" />
                        <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm bg-yellow-50 border border-[#D4AF37] rounded-lg px-3 py-2">
                        <input v-model="form.email_confirmed" type="checkbox" class="rounded" />
                        <span>I confirm <strong>{{ form.email }}</strong> is the correct email for the new sponsor account.</span>
                    </label>
                    <p v-if="form.errors.email_confirmed" class="err">{{ form.errors.email_confirmed }}</p>
                </div>

                <!-- 3. Registration details -->
                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                    <h4 class="font-semibold text-gray-900 pb-2 border-b-2 border-[#D4AF37]">③ Registration</h4>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Event *</label>
                            <select v-model="form.event_id" class="input bg-white">
                                <option value="">Choose event...</option>
                                <option v-for="e in lead.events" :key="e.id" :value="e.id">{{ e.name }}</option>
                            </select>
                            <p v-if="form.errors.event_id" class="err">{{ form.errors.event_id }}</p>
                        </div>
                        <div>
                            <label class="label">Package *</label>
                            <select v-model="form.package_id" @change="onPackageChange" class="input bg-white">
                                <option value="">Choose package...</option>
                                <option v-for="p in packages" :key="p.id" :value="p.id">
                                    {{ p.name }} — ${{ p.price }} ({{ p.assistants_count }} guests)
                                </option>
                            </select>
                            <p v-if="form.errors.package_id" class="err">{{ form.errors.package_id }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="label">Agreed price (USD) *</label>
                            <input v-model.number="form.agreed_price" type="number" min="0" step="0.01" class="input" />
                            <p v-if="form.errors.agreed_price" class="err">{{ form.errors.agreed_price }}</p>
                        </div>
                        <div>
                            <label class="label">Downpayment *</label>
                            <input v-model.number="form.downpayment" type="number" min="0" step="0.01" class="input" />
                            <p v-if="form.errors.downpayment" class="err">{{ form.errors.downpayment }}</p>
                        </div>
                        <div>
                            <label class="label">Installments *</label>
                            <input v-model.number="form.installments_count" type="number" min="1" max="60" class="input" />
                            <p v-if="form.errors.installments_count" class="err">{{ form.errors.installments_count }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="label">Internal notes</label>
                        <textarea v-model="form.notes" rows="3" class="input resize-none"></textarea>
                    </div>
                </div>

                <!-- 4. Documents -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h4 class="font-semibold text-gray-900 pb-2 border-b-2 border-[#D4AF37] mb-4">④ Contract documents (optional)</h4>

                    <label class="inline-flex items-center gap-2 px-3 py-2 border border-dashed border-gray-300 rounded-lg text-sm text-gray-600 cursor-pointer hover:bg-gray-50">
                        <PlusIcon class="w-4 h-4" />
                        Attach files
                        <input type="file" multiple @change="addDocument" class="hidden" />
                    </label>

                    <div v-if="form.documents.length" class="mt-3 space-y-2">
                        <div v-for="(f, i) in form.documents" :key="i"
                            class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-lg text-sm">
                            <div class="flex items-center gap-2">
                                <DocumentTextIcon class="w-4 h-4 text-gray-400" />
                                <span>{{ f.name }}</span>
                                <span class="text-xs text-gray-400">({{ formatFileSize(f.size) }})</span>
                            </div>
                            <button type="button" @click="removeDocument(i)" class="text-red-500 hover:text-red-700">
                                <XMarkIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Warning + Actions -->
                <div class="bg-yellow-50 border border-[#D4AF37] rounded-xl p-4 flex gap-3">
                    <ExclamationTriangleIcon class="w-5 h-5 text-[#D4AF37] flex-shrink-0 mt-0.5" />
                    <p class="text-sm text-gray-700">
                        By submitting, the sponsor user will be created with password <code class="bg-white px-1.5 py-0.5 rounded text-xs border">runway7</code>.
                        Other active leads from <strong>{{ lead.company?.name }}</strong> will be marked as <strong>cerrado</strong>.
                        The onboarding email is sent separately from the Sponsors page (not now).
                    </p>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <Link :href="`/admin/sponsorship/leads/${lead.id}`" class="px-4 py-2.5 text-sm text-gray-600 hover:text-gray-800 font-medium">Cancel</Link>
                    <button type="submit" :disabled="form.processing || !form.email_confirmed"
                        class="px-6 py-2.5 text-sm font-semibold text-white bg-[#D4AF37] rounded-lg hover:bg-yellow-600 disabled:opacity-40">
                        {{ form.processing ? 'Converting...' : 'Close contract & Convert' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<style scoped>
@reference "tailwindcss";
.input { @apply w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400; }
.label { @apply block text-sm font-medium text-gray-700 mb-1.5; }
.err { @apply mt-1 text-red-500 text-xs; }
</style>
