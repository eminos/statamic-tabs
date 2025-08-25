<template>
    <div ref="rootEl">
        <div class="" v-if="isMainTab">
            <Tabs v-model="currentTab" :unmount-on-hide="false">
                <TabList>
                    <TabTrigger v-for="(tab, index) in tabs" :key="tab.id" :name="tab.id">
                        <div class="flex items-center gap-2">
                            <iconify-icon v-if="tab.iconify_icon" :icon="tab.iconify_icon" class="h-4 w-4 text-md" />
                            <Icon v-else-if="tab.icon" :name="tab.icon" />
                            <span>
                                {{ __(tab.name) }}
                            </span>
                        </div>
                    </TabTrigger>
                </TabList>

                <TabContent v-for="(tab, index) in tabs" :key="tab.id" class="tabpanel pt-4" :id="'tab-content-' + tab.id" :name="tab.id">
                    <div class="publish-fields @container w-full"></div>
                </TabContent>
            </Tabs>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick, getCurrentInstance, onBeforeUnmount, useTemplateRef } from 'vue';
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { Tabs, TabContent, TabList, TabTrigger, Icon } from '@statamic/cms/ui';

defineOptions({
    mixins: [Fieldtype]
});

const { proxy } = getCurrentInstance();

const rootEl = useTemplateRef('rootEl');
const isMainTab = ref(false);
const mainTab = ref(null);
const config = proxy.config;

const currentTab = ref(false);

const tab = reactive({
    id: uniqid(),
    handle: config.handle,
    name: config.display,
    icon: config.tab_icon,
    iconify_icon: config.tab_iconify_icon,
    hidden: false
});

const tabs = reactive([]);

const checkIfFirstSibling = () => {
    let element = rootEl.value.closest('.form-group');
    while (element.previousElementSibling) {
        if (element.previousElementSibling.classList.contains('tab-fieldtype')) {
            return false;
        }
        element = element.previousElementSibling;
    }
    return true;
};

const findMainTab = () => {
    let element = rootEl.value.closest('.form-group');
    while (element.previousElementSibling) {
        if (element.previousElementSibling.classList.contains('main-tab')) {
            return element.previousElementSibling;
        }
        element = element.previousElementSibling;
    }
    return null;
};

let mutationObserver = null;

onMounted(() => {

    isMainTab.value = checkIfFirstSibling();

    if (isMainTab.value) {
        const pf = rootEl.value.closest('.form-group');
        pf.classList.add('main-tab');
        pf.dataset.uniqid = tab.id;
        mainTab.value = pf;
    } else {
        mainTab.value = findMainTab();
    }

    if (isMainTab.value) {
        tabs.push(tab);
        currentTab.value = tab.id; // set first tab as active
        proxy.$events.$on('tabs.push-' + tab.id, (incoming) => {
            tabs.push(incoming);
            if (!currentTab.value) currentTab.value = incoming.id;
        });
    } else {
        proxy.$events.$emit('tabs.push-' + mainTab.value.dataset.uniqid, tab);
    }

    nextTick(() => {
        const instructions = rootEl.value.closest('.form-group').querySelector('.help-block');
        if (instructions) {
            if (isMainTab.value) {
                document.getElementById('tab-content-' + tabs[0].id).prepend(instructions);
                proxy.$events.$on('tabs.prepend-instructions-' + tab.id, (element, id) => {
                    document.getElementById('tab-content-' + id).prepend(element);
                });
            } else {
                proxy.$events.$emit('tabs.prepend-instructions-' + mainTab.value.dataset.uniqid, instructions, tab.id);
            }
        }
    });

    let element = rootEl.value.closest('.form-group');
    const nextSiblings = [];
    while (element.nextElementSibling) {
        if (element.nextElementSibling.classList.contains('tab-fieldtype')) break;
        nextSiblings.push(element.nextElementSibling);
        element = element.nextElementSibling;
    }

    nextTick(() => {
        if (isMainTab.value) {
            nextSiblings.forEach(ns => {
                document.getElementById('tab-content-' + tabs[0].id)
                    .querySelector('.publish-fields')
                    .appendChild(ns);
            });
            proxy.$events.$on('tabs.append-' + tab.id, (el, id) => {
                document.getElementById('tab-content-' + id)
                    .querySelector('.publish-fields')
                    .appendChild(el);
            });
        } else {
            nextSiblings.forEach(ns => {
                proxy.$events.$emit('tabs.append-' + mainTab.value.dataset.uniqid, ns, tab.id);
            });
        }
    });

    if (isMainTab.value) {
        const label = rootEl.value.closest('.form-group').querySelector('label');
        if (label) label.classList.add('super-invisible');
    }

    if (!isMainTab.value) {
        rootEl.value.closest('.form-group').classList.add('super-invisible');
    }

    nextTick(() => {
        const publishField = rootEl.value.closest('.form-group');
        const display = window.getComputedStyle(publishField).display;
        tab.hidden = display === 'none';
    });

    nextTick(() => {
        const publishField = rootEl.value.closest('.form-group');
        mutationObserver = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    const display = window.getComputedStyle(publishField).display;
                    tab.hidden = display === 'none';
                }
            });
        });
        mutationObserver.observe(publishField, { attributes: true, attributeFilter: ['style'] });
    });
});

onBeforeUnmount(() => {
    if (mutationObserver) mutationObserver.disconnect();
    if (isMainTab.value) {
        proxy.$events.$off && proxy.$events.$off('tabs.push-' + tab.id);
        proxy.$events.$off && proxy.$events.$off('tabs.prepend-instructions-' + tab.id);
        proxy.$events.$off && proxy.$events.$off('tabs.append-' + tab.id);
    }
});
</script>