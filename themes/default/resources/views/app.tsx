import '../assets/css/app.css';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePage } from './lib/resolve-page';
import './lib/route';

createInertiaApp({
    resolve: resolvePage,
    progress: {
        delay: 150,
        color: '#6366f1',
        includeCSS: true,
        showSpinner: true,
    },
    setup({ el, App, props }) {
        const routes = (props.initialPage.props as { routes?: Record<string, string> }).routes;

        window.__routes = routes ?? {};

        createRoot(el).render(<App {...props} />);
    },
});
