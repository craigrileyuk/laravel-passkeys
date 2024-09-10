<template>
	<section>
		<header>
			<h2 class="text-lg font-medium text-gray-900">Manage Passkeys</h2>

			<p class="mt-1 text-sm text-gray-600">
				Passkeys allow for a more secure, seamless authentication experience on supported devices.
			</p>
		</header>

		<form @submit.prevent="createPasskey" class="mt-6 space-y-6">
			<div>
				<InputLabel for="passkey_name" value="Passkey Name" />

				<TextInput id="passkey_name" v-model="form.name" type="text" class="mt-1 block w-full" />

				<InputError :message="form.errors.name" class="mt-2" />
			</div>

			<div class="flex items-center gap-4">
				<PrimaryButton :disabled="form.processing || !form.name">Create Passkey</PrimaryButton>

				<Transition
					enter-active-class="transition ease-in-out"
					enter-from-class="opacity-0"
					leave-active-class="transition ease-in-out"
					leave-to-class="opacity-0"
				>
					<p v-if="form.recentlySuccessful" class="text-sm text-gray-600">Saved.</p>
				</Transition>

				<InputError :message="form.errors.passkey" class="mt-2" />
			</div>
		</form>

		<div class="mt-4">
			<h3 class="mb-4 text-base font-medium text-gray-900">Your Passkeys</h3>
			<div v-for="passkey in props.passkeys" class="mb-4 flex justify-between">
				<div>
					<p class="font-bold">{{ passkey.name }}</p>
					<p class="opacity-50">Added {{ formatTimeAgo(new Date(passkey.created_at)) }}</p>
				</div>
				<div>
					<DangerButton class="ms-3" @click="removeKey(passkey)"> Remove </DangerButton>
				</div>
			</div>
		</div>
	</section>
</template>

<script setup>
import { useRoute } from "@adminui/inertia-routes";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import DangerButton from "@/Components/DangerButton.vue";
import { formatTimeAgo } from "@vueuse/core";
import { useForm, router } from "@inertiajs/vue3";
import { usePasskeys } from "composables/usePasskeys";
import { ref } from "vue";

const route = useRoute();
const props = defineProps({
	passkeys: {
		type: Array,
		default: () => [],
	},
});

const form = useForm({
	name: "",
	passkey: null,
});

const { register: registerPasskey } = usePasskeys();

const createPasskey = async () => {
	try {
		const result = await registerPasskey(form.name);
		form.passkey = JSON.stringify(result);
		form.post(route("passkeys.store"), {
			preserveScroll: true,
			onSuccess: () => form.reset(),
		});
	} catch (e) {
		form.errors.passkey = "Passkey creation was cancelled";
	}
};

const removeKey = (passkey) => {
	router.delete(
		route("passkeys.destroy", {
			passkey: passkey.id,
		}),
		{
			only: ["passkeys"],
			preserveScroll: true,
		},
	);
};
</script>
