import "./bootstrap";
import "../css/app.css";
import { useInertiaRoutes } from "@adminui/inertia-routes";

import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob("./pages/**/*.vue")
        ),
    setup({ el, App, props, plugin }) {
        const inertiaRoutesPlugin = useInertiaRoutes(props);

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(inertiaRoutesPlugin)
            .mount(el);
    },
    progress: {
        color: "#4B5563",
    },
});
