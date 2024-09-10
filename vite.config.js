import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import { resolve } from "node:path";

export default defineConfig({
	resolve: {
		alias: {
			composables: resolve("./resources/js/composables"),
		},
	},
	plugins: [
		laravel({
			input: "resources/js/app.js",
			refresh: true,
		}),
		vue({
			template: {
				transformAssetUrls: {
					base: null,
					includeAbsolute: false,
				},
			},
		}),
	],
});
