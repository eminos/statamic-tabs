import TabFieldtype from './compontents/TabFieldtype.vue';

Statamic.booting(() => {
    Statamic.$components.register('tab-fieldtype', TabFieldtype);
});
