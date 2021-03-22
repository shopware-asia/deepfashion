import SearchByImageFormPlugin from './plugins/search-by-image-form.plugin';
const PluginManager = window.PluginManager;

PluginManager.register('SearchByImageForm', SearchByImageFormPlugin, '[data-search-by-image-form]');
