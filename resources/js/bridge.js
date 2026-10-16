import $ from 'jquery';
import { createApp } from 'vue';

// jQuery globals for inline scripts / plugins
window.$ = window.jQuery = $;

// Auto-register every .vue file in ./components
const modules = import.meta.glob('./components/*.vue', { eager: true });
const registry = {};
for (const path in modules) {
  registry[path.split('/').pop().replace('.vue', '')] = modules[path].default;
}

/** Mount <div data-vue="Name" data-props='{"a":1}'> islands. */
export function mountIslands(root = document) {
  const apps = [];
  $(root).find('[data-vue]').each(function () {
    const el = this;
    if (el.__vue_app__) return;
    const component = registry[el.dataset.vue];
    if (!component) {
      console.warn(`[bridge] Vue component "${el.dataset.vue}" not found`);
      return;
    }
    let props = {};
    try { props = JSON.parse(el.dataset.props || '{}'); } catch { /* ignore bad JSON */ }
    const app = createApp(component, props);
    app.mount(el);
    apps.push(app);
  });
  return apps;
}

window.mountIslands = mountIslands;
