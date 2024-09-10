import { ref, readonly } from "vue";
import { useRoute } from "@adminui/inertia-routes";
import { startRegistration, browserSupportsWebAuthn, startAuthentication } from "@simplewebauthn/browser";

export const usePasskeys = () => {
	const route = useRoute();

	const register = async (name) => {
		if (!browserSupportsWebAuthn()) {
			return;
		}

		const options = await axios
			.get(
				route("passkeys.register", {
					name,
				}),
			)
			.then((res) => {
				return res.data;
			})
			.catch((err) => {
				console.log(err);
			});

		try {
			const passkey = await startRegistration(options);
			return passkey;
		} catch (e) {
			throw e;
		}
	};

	const authenticate = async () => {
		const options = await axios.get(route("passkeys.authenticate"));
		const answer = await startAuthentication(options.data);
		return answer;
	};

	return {
		register,
		authenticate,
	};
};
