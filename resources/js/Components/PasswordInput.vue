<script setup>
import { ref } from 'vue';
import { Eye, EyeOff } from '@lucide/vue';

// A password field with a show / hide (eye) button. Use it like a normal <input>:
// <PasswordInput v-model="form.password" autocomplete="new-password" :class="input" />
defineOptions({ inheritAttrs: false });
defineProps({ modelValue: { type: String, default: '' } });
defineEmits(['update:modelValue']);

const visible = ref(false);
</script>

<template>
    <div class="relative">
        <input
            v-bind="$attrs"
            :value="modelValue"
            :type="visible ? 'text' : 'password'"
            class="pr-11"
            @input="$emit('update:modelValue', $event.target.value)"
        >
        <button
            type="button"
            class="absolute top-1/2 right-1 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 focus:ring-2 focus:ring-green-500/30 focus:outline-none"
            :aria-label="visible ? 'Hide password' : 'Show password'"
            :aria-pressed="visible"
            @click="visible = !visible"
        >
            <EyeOff v-if="visible" class="h-4 w-4" />
            <Eye v-else class="h-4 w-4" />
        </button>
    </div>
</template>
