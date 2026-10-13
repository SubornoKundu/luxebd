<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ImagePlus, Images, Plus, Star, Trash2, X } from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { uploadProblem } from '@/uploadGuard';

const props = defineProps({
    product: { type: Object, default: null },
    categories: Array,
    brands: Array,
});

const editing = computed(() => props.product !== null);

const form = useForm({
    _method: editing.value ? 'put' : 'post',
    name: props.product?.name ?? '',
    category_id: props.product?.category_id ?? '',
    brand_id: props.product?.brand_id ?? '',
    description: props.product?.description ?? '',
    price: props.product?.price ?? '',
    discount_price: props.product?.discount_price ?? '',
    stock: props.product?.stock ?? 0,
    shipping_info: props.product?.shipping_info ?? '',
    specifications: (props.product?.specifications ?? []).map((r) => ({ label: r.label, value: r.value })),
    variants: (props.product?.variants ?? []).map((v) => ({
        id: v.id, type: v.type ?? '', size: v.size ?? '', color: v.color ?? '', extra_price: v.extra_price ?? 0, stock: v.stock ?? 0,
        image: null, existing_image: v.image ?? null, preview: v.image ?? null, remove_image: false,
    })),
    sync_options: 1,
    is_featured: props.product?.is_featured ?? false,
    is_active: props.product?.is_active ?? true,
    image: null,
    gallery: [],
    remove_gallery: [],
    main_gallery_id: null,
});

const MAX_GALLERY = 10;
const preview = ref(props.product?.image ?? null);

/* ---------- extra pictures ---------- */
// saved extra pictures still on the product, and new files waiting to be uploaded
const savedGallery = ref((props.product?.images ?? []).map((i) => ({ id: i.id, path: i.path })));
const newGallery = ref([]);
const swapped = ref(null); // { id } of the saved picture promoted to main (not saved yet)
const galleryCount = computed(() => savedGallery.value.length + newGallery.value.length);
const galleryRoom = computed(() => MAX_GALLERY - galleryCount.value);
const galleryNote = ref('');

function undoSwap() {
    if (!swapped.value) return;
    const item = savedGallery.value.find((g) => g.id === swapped.value.id);
    if (item) [item.path, preview.value] = [preview.value, item.path];
    swapped.value = null;
    form.main_gallery_id = null;
}

function pickImage(event) {
    const file = event.target.files[0];
    const problem = uploadProblem(file);
    if (problem) {
        event.target.value = '';
        form.setError('image', problem);
        return;
    }
    form.clearErrors('image');
    if (file) {
        undoSwap();
        form.image = file;
        preview.value = URL.createObjectURL(file);
    } else {
        form.image = null;
    }
}

function pickGallery(event) {
    const files = [...event.target.files];
    event.target.value = '';
    galleryNote.value = '';
    const tooBig = files.map(uploadProblem).find(Boolean);
    if (tooBig) {
        galleryNote.value = tooBig;
        return;
    }
    const take = files.slice(0, Math.max(0, galleryRoom.value));
    take.forEach((file) => newGallery.value.push({ file, url: URL.createObjectURL(file) }));
    if (files.length > take.length) galleryNote.value = `Only ${MAX_GALLERY} extra pictures are allowed - ${files.length - take.length} not added.`;
}

function removeSaved(id) {
    if (swapped.value?.id === id) undoSwap();
    savedGallery.value = savedGallery.value.filter((g) => g.id !== id);
    form.remove_gallery.push(id);
    galleryNote.value = '';
}

function removeNew(i) {
    URL.revokeObjectURL(newGallery.value[i].url);
    newGallery.value.splice(i, 1);
    galleryNote.value = '';
}

function makeMain(item) {
    undoSwap();
    [item.path, preview.value] = [preview.value, item.path];
    swapped.value = { id: item.id };
    form.main_gallery_id = item.id;
}

onBeforeUnmount(() => newGallery.value.forEach((g) => URL.revokeObjectURL(g.url)));

const hasVariants = computed(() => form.variants.length > 0);
const variantStock = computed(() => form.variants.reduce((sum, v) => sum + (parseInt(v.stock, 10) || 0), 0));

function addVariant() {
    form.variants.push({ id: null, type: '', size: '', color: '', extra_price: 0, stock: 0, image: null, existing_image: null, preview: null, remove_image: false });
}

function removeVariant(i) {
    form.variants.splice(i, 1);
}

function pickVariantImage(i, event) {
    const file = event.target.files[0];
    if (!file) return;
    const problem = uploadProblem(file);
    if (problem) {
        event.target.value = '';
        form.setError(`variants.${i}.image`, problem);
        return;
    }
    form.variants[i].image = file;
    form.variants[i].preview = URL.createObjectURL(file);
    form.variants[i].remove_image = false;
}

function clearVariantImage(i) {
    Object.assign(form.variants[i], { image: null, preview: null, remove_image: true });
}

function addSpec() {
    form.specifications.push({ label: '', value: '' });
}

function submit() {
    // The product's stock is the sum of its variants' stock.
    if (hasVariants.value) form.stock = variantStock.value;
    form.transform((data) => ({
        ...data,
        gallery: newGallery.value.map((g) => g.file),
        main_gallery_id: data.main_gallery_id ?? undefined,
        variants: data.variants.map(({ preview, existing_image, ...v }) => ({ ...v, remove_image: v.remove_image ? 1 : 0, image: v.image ?? undefined })),
    }));
    form.post(editing.value ? `/admin/products/${props.product.id}` : '/admin/products', { forceFormData: true });
}

const input = 'w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 focus:outline-none';
</script>

<template>
    <Head :title="editing ? 'Edit product' : 'Add product'" />

    <AdminLayout :title="editing ? 'Edit product' : 'Add product'">
        <form class="grid gap-4 sm:gap-6 lg:grid-cols-3" @submit.prevent="submit">
            <div class="min-w-0 space-y-4 rounded-2xl bg-white p-4 shadow-sm sm:p-6 lg:col-span-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Name</label>
                    <input v-model="form.name" placeholder="e.g. Cotton Panjabi - Eid Collection" type="text" :class="input">
                    <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Category</label>
                        <select v-model="form.category_id" :class="input"><option value="" disabled>Select...</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select>
                        <p v-if="form.errors.category_id" class="mt-1 text-xs text-rose-600">{{ form.errors.category_id }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Brand</label>
                        <select v-model="form.brand_id" :class="input"><option value="" disabled>Select...</option><option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option></select>
                        <p v-if="form.errors.brand_id" class="mt-1 text-xs text-rose-600">{{ form.errors.brand_id }}</p>
                    </div>
                </div>
                <div class="grid gap-4 min-[480px]:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Price (BDT)</label>
                        <input v-model="form.price" placeholder="e.g. 1500" type="number" inputmode="decimal" min="1" step="0.01" :class="input">
                        <p v-if="form.errors.price" class="mt-1 text-xs text-rose-600">{{ form.errors.price }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Sale price</label>
                        <input v-model="form.discount_price" placeholder="optional, e.g. 1200" type="number" inputmode="decimal" min="0.01" step="0.01" :class="input">
                        <p v-if="form.errors.discount_price" class="mt-1 text-xs text-rose-600">{{ form.errors.discount_price }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Stock</label>
                        <input v-if="!hasVariants" v-model="form.stock" placeholder="e.g. 50" type="number" inputmode="numeric" min="0" :class="input">
                        <input v-else :value="variantStock" type="number" disabled :class="[input, 'bg-gray-50 text-gray-500']" title="Sum of variant stock">
                        <p v-if="form.errors.stock" class="mt-1 text-xs text-rose-600">{{ form.errors.stock }}</p>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Description (Overview tab)</label>
                    <textarea v-model="form.description" placeholder="Describe the product: material, features, what is in the box..." rows="6" :class="input" />
                    <p v-if="form.errors.description" class="mt-1 text-xs text-rose-600">{{ form.errors.description }}</p>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label class="text-sm font-medium text-gray-700">Specifications</label>
                        <button type="button" class="flex min-h-9 items-center gap-1 rounded-full border border-gray-200 px-3 text-xs font-semibold text-gray-700 hover:bg-gray-50" @click="addSpec"><Plus class="h-3.5 w-3.5" /> Add row</button>
                    </div>
                    <div v-for="(row, i) in form.specifications" :key="i" class="mb-2 flex gap-2">
                        <input v-model="row.label" placeholder="e.g. Fabric" type="text" :class="input">
                        <input v-model="row.value" placeholder="e.g. 100% cotton" type="text" :class="input">
                        <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-rose-500 hover:bg-rose-50" aria-label="Remove row" @click="form.specifications.splice(i, 1)"><X class="h-4 w-4" /></button>
                    </div>
                    <p v-if="!form.specifications.length" class="text-xs text-gray-400">No specifications yet.</p>
                    <p v-for="(msg, key) in form.errors" v-show="String(key).startsWith('specifications')" :key="key" class="text-xs text-rose-600">{{ msg }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Shipping terms <span class="font-normal text-gray-400">(leave empty for the store default)</span></label>
                    <textarea v-model="form.shipping_info" placeholder="e.g. Delivery 1-2 days in Dhaka, 3-5 days outside Dhaka" rows="3" :class="input" />
                    <p v-if="form.errors.shipping_info" class="mt-1 text-xs text-rose-600">{{ form.errors.shipping_info }}</p>
                </div>

                <div class="rounded-xl border border-gray-200 p-3 sm:p-4">
                    <div class="mb-1 flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-800">Variants <span class="font-normal text-gray-400">(size in ml, type)</span></h3>
                        <button type="button" class="flex min-h-9 items-center gap-1 rounded-full bg-green-600 px-3 text-xs font-semibold text-white hover:bg-green-700" @click="addVariant"><Plus class="h-3.5 w-3.5" /> Add variant</button>
                    </div>
                    <p class="mb-3 text-xs text-gray-400">Each variant has its own stock and optional extra price and photo. Product stock becomes the total.</p>
                    <p v-if="form.errors.variants" class="mb-2 text-xs text-rose-600">{{ form.errors.variants }}</p>
                    <ul class="space-y-3">
                        <li v-for="(v, i) in form.variants" :key="v.id ?? `new-${i}`" class="rounded-xl bg-gray-50 p-3">
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                <input v-model="v.type" placeholder="Type, e.g. EDP" type="text" aria-label="Type" :class="input">
                                <input v-model="v.size" placeholder="Size, e.g. 50 ml" type="text" aria-label="Size" :class="input">
                                <input v-model="v.color" placeholder="Colour (optional)" type="text" aria-label="Colour" :class="input">
                                <input v-model="v.extra_price" placeholder="Extra price" type="number" inputmode="decimal" min="0" step="0.01" aria-label="Extra price" :class="input">
                                <input v-model="v.stock" placeholder="Stock" type="number" inputmode="numeric" min="0" aria-label="Stock" :class="input">
                                <button type="button" class="flex min-h-10 items-center justify-center gap-1 rounded-lg border border-rose-200 text-xs font-semibold text-rose-600 hover:bg-rose-50" @click="removeVariant(i)"><Trash2 class="h-3.5 w-3.5" /> Remove</button>
                            </div>
                            <div class="mt-2 flex items-center gap-3">
                                <img v-if="v.preview" :src="v.preview" class="h-12 w-12 rounded-lg bg-white object-cover" alt="">
                                <label class="flex min-h-9 cursor-pointer items-center gap-1.5 rounded-full border border-gray-300 bg-white px-3 text-xs font-medium text-gray-700 hover:bg-gray-50"><ImagePlus class="h-3.5 w-3.5" /> {{ v.preview ? 'Change photo' : 'Variant photo' }}
                                    <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="pickVariantImage(i, $event)">
                                </label>
                                <button v-if="v.preview" type="button" class="min-h-9 text-xs text-gray-500 hover:text-rose-600" @click="clearVariantImage(i)">Remove photo</button>
                            </div>
                            <p v-for="(msg, key) in form.errors" v-show="String(key).startsWith(`variants.${i}.`)" :key="key" class="mt-1 text-xs text-rose-600">{{ msg }}</p>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="h-fit min-w-0 space-y-4 rounded-2xl bg-white p-4 shadow-sm sm:p-6">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Image <span v-if="!editing" class="text-rose-500">*</span></label>
                    <img v-if="preview" :src="preview" class="mb-2 h-48 w-full rounded-lg bg-gray-100 object-cover sm:h-56 lg:h-40">
                    <input type="file" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-full file:border-0 file:bg-green-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-green-700" @change="pickImage">
                    <p class="mt-1 text-xs text-gray-400">JPG, PNG or WebP, up to 4 MB. Any size or shape works: it is made square automatically and nothing is cut off.</p>
                    <p v-if="form.errors.image" class="mt-1 text-xs text-rose-600">{{ form.errors.image }}</p>
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <label class="flex items-center gap-1.5 text-sm font-medium text-gray-700"><Images class="h-4 w-4" /> More pictures</label>
                        <span class="text-xs text-gray-400">{{ galleryCount }} / {{ MAX_GALLERY }}</span>
                    </div>
                    <ul v-if="savedGallery.length || newGallery.length" class="mb-2 grid grid-cols-3 gap-2">
                        <li v-for="g in savedGallery" :key="'s' + g.id" class="min-w-0">
                            <div class="relative aspect-square overflow-hidden rounded-lg bg-gray-100">
                                <img :src="g.path" alt="" class="h-full w-full object-cover">
                                <button type="button" class="absolute top-1 right-1 flex h-8 w-8 items-center justify-center rounded-full bg-black/60 text-white hover:bg-rose-600" aria-label="Remove this picture" @click="removeSaved(g.id)"><X class="h-4 w-4" /></button>
                            </div>
                            <button type="button" :disabled="!!form.image" class="mt-1 flex min-h-9 w-full items-center justify-center gap-1 rounded-md border border-gray-200 text-[11px] font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-40" @click="makeMain(g)"><Star class="h-3 w-3" /> Make main</button>
                        </li>
                        <li v-for="(g, i) in newGallery" :key="'n' + i" class="min-w-0">
                            <div class="relative aspect-square overflow-hidden rounded-lg bg-gray-100 ring-2 ring-green-500/40">
                                <img :src="g.url" alt="" class="h-full w-full object-cover">
                                <button type="button" class="absolute top-1 right-1 flex h-8 w-8 items-center justify-center rounded-full bg-black/60 text-white hover:bg-rose-600" aria-label="Remove this picture" @click="removeNew(i)"><X class="h-4 w-4" /></button>
                            </div>
                            <p class="mt-1 text-center text-[11px] text-green-700">New</p>
                        </li>
                    </ul>
                    <label class="flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50" :class="galleryRoom <= 0 ? 'pointer-events-none opacity-50' : ''">
                        <ImagePlus class="h-4 w-4" /> Add pictures
                        <input type="file" multiple accept="image/png,image/jpeg,image/webp" class="sr-only" :disabled="galleryRoom <= 0" @change="pickGallery">
                    </label>
                    <p class="mt-1 text-xs text-gray-400">Pick several at once. JPG, PNG or WebP, up to 4 MB each, any size (made square automatically). Shoppers can tap them to view full screen.</p>
                    <p v-if="galleryNote" class="mt-1 text-xs text-amber-600">{{ galleryNote }}</p>
                    <p v-if="form.errors.gallery" class="mt-1 text-xs text-rose-600">{{ form.errors.gallery }}</p>
                    <template v-for="(msg, key) in form.errors" :key="key"><p v-if="String(key).startsWith('gallery.')" class="mt-1 text-xs text-rose-600">{{ msg }}</p></template>
                </div>
                <label class="flex items-center gap-2 py-1 text-sm text-gray-700"><input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-green-600"> Visible in the store</label>
                <label class="flex items-center gap-2 py-1 text-sm text-gray-700"><input v-model="form.is_featured" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-green-600"> Featured on home page</label>
                <div class="flex flex-col-reverse gap-2 pt-2 min-[480px]:flex-row">
                    <button type="submit" :disabled="form.processing" class="flex-1 rounded-full bg-green-600 py-2.5 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60">{{ form.processing ? 'Saving...' : 'Save product' }}</button>
                    <Link href="/admin/products" class="rounded-full border border-gray-300 px-5 py-2.5 text-center text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</Link>
                </div>
            </div>
        </form>
    </AdminLayout>
</template>
