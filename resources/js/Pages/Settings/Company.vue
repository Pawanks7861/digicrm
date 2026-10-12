<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
    can: { type: Object, required: true },
});

const form = useForm({
    trading_name: props.settings.trading_name || '',
    legal_name: props.settings.legal_name || '',
    email: props.settings.email || '',
    phone: props.settings.phone || '',
    website: props.settings.website || '',
    address: props.settings.address || '',
    city: props.settings.city || '',
    state: props.settings.state || '',
    country: props.settings.country || '',
    pin: props.settings.pin || '',
    gstin: props.settings.gstin || '',
    pan: props.settings.pan || '',
});

const logoPreview = ref(props.settings.logo_url || '');
const faviconPreview = ref(props.settings.favicon_url || '');
const logoForm = useForm({ logo: null });
const faviconForm = useForm({ favicon: null });

function pickLogo(file) {
    if (!file) return;
    logoForm.logo = file;
    logoPreview.value = URL.createObjectURL(file);
}
function pickFavicon(file) {
    if (!file) return;
    faviconForm.favicon = file;
    faviconPreview.value = URL.createObjectURL(file);
}
function onDrop(event, kind) {
    const file = event.dataTransfer?.files?.[0];
    if (kind === 'logo') pickLogo(file);
    else pickFavicon(file);
}
</script>

<template>
    <Head title="Company Settings" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <p class="text-sm text-mute">Settings</p>
            <h1 class="text-2xl font-semibold tracking-tight">Company Settings</h1>
        </div>

        <div class="grid gap-4 xl:grid-cols-[320px_minmax(0,1fr)]">
            <section class="rounded-card bg-card p-5 shadow-sm">
                <h2 class="text-sm font-semibold">Branding</h2>
                <p class="mt-1 text-sm text-mute">Logo and favicon used on the login page, sidebar, portal, and documents.</p>

                <div
                    class="mt-4 rounded-card border border-dashed border-line bg-mist/60 p-4 text-center"
                    @dragover.prevent
                    @drop.prevent="onDrop($event, 'logo')"
                >
                    <img v-if="logoPreview" :src="logoPreview" alt="Current logo" class="mx-auto max-h-56 w-auto max-w-full object-contain" />
                    <div v-else class="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl bg-sun/70 text-lg font-semibold">Logo</div>
                    <p class="mt-3 text-sm font-medium">Current logo</p>
                    <label v-if="can.branding" class="mt-2 inline-block cursor-pointer text-sm text-accent">
                        Upload new logo
                        <input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="sr-only" @change="pickLogo($event.target.files[0])" />
                    </label>
                    <p v-if="logoForm.errors.logo" class="mt-2 text-sm text-red-700">{{ logoForm.errors.logo }}</p>
                    <div v-if="can.branding" class="mt-3 flex justify-center gap-2">
                        <PrimaryButton type="button" :disabled="!logoForm.logo || logoForm.processing" @click="logoForm.post(route('settings.company.logo'), { forceFormData: true })">Save logo</PrimaryButton>
                        <button v-if="settings.logo_url" type="button" class="rounded-full px-3 py-2 text-sm text-mute" @click="logoForm.delete(route('settings.company.logo.remove'))">Remove logo</button>
                    </div>
                </div>

                <div
                    class="mt-4 rounded-card border border-dashed border-line bg-mist/60 p-4 text-center"
                    @dragover.prevent
                    @drop.prevent="onDrop($event, 'favicon')"
                >
                    <img v-if="faviconPreview" :src="faviconPreview" alt="Current favicon" class="mx-auto h-12 w-12 object-contain" />
                    <div v-else class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-white text-xs text-mute">Icon</div>
                    <p class="mt-3 text-sm font-medium">Favicon</p>
                    <p class="text-xs text-mute">Square PNG, ICO, or a safe SVG. 32, 64, or 128 pixels work well.</p>
                    <label v-if="can.branding" class="mt-2 inline-block cursor-pointer text-sm text-accent">
                        Upload favicon
                        <input type="file" accept="image/png,image/x-icon,image/vnd.microsoft.icon,.ico,image/svg+xml" class="sr-only" @change="pickFavicon($event.target.files[0])" />
                    </label>
                    <p v-if="faviconForm.errors.favicon" class="mt-2 text-sm text-red-700">{{ faviconForm.errors.favicon }}</p>
                    <div v-if="can.branding" class="mt-3 flex justify-center gap-2">
                        <PrimaryButton type="button" :disabled="!faviconForm.favicon || faviconForm.processing" @click="faviconForm.post(route('settings.company.favicon'), { forceFormData: true })">Save favicon</PrimaryButton>
                        <button v-if="settings.favicon_url" type="button" class="rounded-full px-3 py-2 text-sm text-mute" @click="faviconForm.delete(route('settings.company.favicon.remove'))">Remove favicon</button>
                    </div>
                </div>
            </section>

            <section class="rounded-card bg-card p-5 shadow-sm">
                <h2 class="text-sm font-semibold">Company details</h2>
                <p class="mt-1 text-sm text-mute">These fields are the same company record used for billing documents. Tax rates and bank details stay in Finance.</p>
                <form class="mt-4 grid gap-3 sm:grid-cols-2" @submit.prevent="form.put(route('settings.company.update'))">
                    <div><InputLabel value="Company name" /><TextInput v-model="form.trading_name" class="mt-1 w-full" :disabled="!can.manage" /></div>
                    <div><InputLabel value="Legal company name" /><TextInput v-model="form.legal_name" class="mt-1 w-full" :disabled="!can.manage" required /></div>
                    <div><InputLabel value="Email" /><TextInput v-model="form.email" type="email" class="mt-1 w-full" :disabled="!can.manage" /></div>
                    <div><InputLabel value="Phone" /><TextInput v-model="form.phone" class="mt-1 w-full" :disabled="!can.manage" /></div>
                    <div class="sm:col-span-2"><InputLabel value="Website" /><TextInput v-model="form.website" class="mt-1 w-full" :disabled="!can.manage" /></div>
                    <div class="sm:col-span-2"><InputLabel value="Address" /><textarea v-model="form.address" rows="2" class="mt-1 w-full rounded-xl border-line text-sm" :disabled="!can.manage" /></div>
                    <div><InputLabel value="City" /><TextInput v-model="form.city" class="mt-1 w-full" :disabled="!can.manage" /></div>
                    <div><InputLabel value="State" /><TextInput v-model="form.state" class="mt-1 w-full" :disabled="!can.manage" /></div>
                    <div><InputLabel value="Country" /><TextInput v-model="form.country" class="mt-1 w-full" :disabled="!can.manage" /></div>
                    <div><InputLabel value="PIN / ZIP" /><TextInput v-model="form.pin" class="mt-1 w-full" :disabled="!can.manage" /></div>
                    <div><InputLabel value="GSTIN" /><TextInput v-model="form.gstin" class="mt-1 w-full" :disabled="!can.manage" /></div>
                    <div><InputLabel value="PAN" /><TextInput v-model="form.pan" class="mt-1 w-full" :disabled="!can.manage" /></div>
                    <div v-if="can.manage" class="sm:col-span-2">
                        <PrimaryButton :disabled="form.processing">Save company details</PrimaryButton>
                    </div>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
