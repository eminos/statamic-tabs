<template>
    <div ref="rootEl">
        <div class="" v-if="isMainTab">
            <Tabs v-model="currentTab" :unmount-on-hide="false">
                <TabList>
                    <TabTrigger v-for="tab in tabs" :key="tab.id" :name="tab.id">
                        <div class="flex items-center gap-2">
                            <IconifyIcon v-if="tab.iconify_icon" :icon="tab.iconify_icon" class="size-4 shrink-0" />
                            <Icon v-else-if="tab.icon" :name="tab.icon" />
                            <span>
                                {{ __(tab.name) }}
                            </span>
                        </div>
                    </TabTrigger>
                </TabList>

                <TabContent v-for="tab in tabs" :key="tab.id" class="tabpanel pt-4" :id="'tab-content-' + tab.id" :name="tab.id">
                    <div class="publish-fields @container w-full"></div>
                </TabContent>
            </Tabs>
        </div>

        <div v-if="isMainTab && containerRef && isReinitializing" class="absolute inset-0 z-10 flex items-center justify-center bg-white/75 dark:bg-black/75 backdrop-blur-[2px] rounded transition-all duration-200">
            <div class="bg-white dark:bg-black px-3 py-1.5 rounded shadow-sm border border-gray-200 text-xs font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <svg class="animate-spin h-3 w-3 text-gray-500 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Re-initializing tabs...
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick, getCurrentInstance, onBeforeUnmount, h } from 'vue';
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { events } from '@statamic/cms/api';
import { Tabs, TabContent, TabList, TabTrigger, Icon } from '@statamic/cms/ui';

defineOptions({
    mixins: [Fieldtype]
});

// Render function wrapper to prevent "Failed to resolve component" warning
const IconifyIcon = (props, { attrs }) => h('iconify-icon', { ...props, ...attrs });

const { proxy } = getCurrentInstance();

const rootEl = ref(null);
const isMainTab = ref(false);
const mainTab = ref(null);
const config = proxy.config;

const currentTab = ref(false);

const EVENTS = {
    push: (id) => `tabs.push-${id}`,
    prependInstructions: (id) => `tabs.prepend-instructions-${id}`,
    append: (id) => `tabs.append-${id}`,
    release: (id) => `tabs.release-${id}`,
    globalRestore: 'tabs.force-restore-sibling-sets',
};

const getFormGroup = () => (rootEl.value ? rootEl.value.closest('.form-group') : null);
const getBardContainer = () => (rootEl.value ? rootEl.value.closest('.bard-fieldtype') : null);
const getTabContentEl = (id) => document.getElementById('tab-content-' + id);

const tab = reactive({
    id: uniqid(),
    handle: config.handle,
    name: config.display,
    icon: config.tab_icon,
    iconify_icon: config.tab_iconify_icon,
    hidden: false,
});

const tabs = reactive([]);
const movedNodes = []; // { element, placeholder }
const timers = [];

const isReinitializing = ref(false);
const containerRef = ref(null);

// Timer Helper
const registerTimeout = (fn, delay) => {
    const id = setTimeout(fn, delay);
    timers.push({ id });
    return id;
};

const clearAllTimers = () => {
    timers.forEach(t => clearTimeout(t.id));
    timers.length = 0;
};

let releaseEventName = null;

const cleanupListeners = () => {
    if (isMainTab.value) {
        events.$off(EVENTS.push(tab.id));
        events.$off(EVENTS.prependInstructions(tab.id));
        events.$off(EVENTS.append(tab.id));
    }

    if (releaseEventName) {
        events.$off(releaseEventName);
        releaseEventName = null;
    }
};

const safeMove = (element, targetContainer, method = 'append') => {
    if (!element || !targetContainer) return;
    
    // Placeholder comment lets us restore nodes to their exact original spot
    const placeholder = document.createComment(`tab-placeholder:${element.id || element.className}`);
    
    // Insert placeholder before move
    if (element.parentNode) {
        element.parentNode.insertBefore(placeholder, element);
        movedNodes.push({ element, placeholder });
        
        if (method === 'prepend') {
            targetContainer.prepend(element);
        } else {
            targetContainer.appendChild(element);
        }
    }
};

let isRestoring = false;

const ensureBardInstanceId = () => {
    const bardContainer = getBardContainer();
    if (!bardContainer) return null;

    if (!bardContainer.dataset.bardInstanceId) {
        bardContainer.dataset.bardInstanceId = uniqid();
    }

    return bardContainer.dataset.bardInstanceId;
};

const restoreDom = (shouldEmitGlobal = true) => {
    // Prevent recursion
    if (isRestoring) return;
    isRestoring = true;

    try {
        // Notify other tab fieldtypes in the same Bard instance to flatten/restore too.
        if (shouldEmitGlobal) {
            const bardId = ensureBardInstanceId();
            events.$emit(EVENTS.globalRestore, { originId: tab.id, bardId });
        }

        // Notify local siblings (within this publish form) to restore.
        if (isMainTab.value) {
            events.$emit(EVENTS.release(tab.id));
        }

        // 1. Restore Moved Nodes
        if (movedNodes.length > 0) {
            [...movedNodes].reverse().forEach(({ element, placeholder }) => {
                if (placeholder && placeholder.parentNode) {
                    placeholder.parentNode.replaceChild(element, placeholder);
                }
            });
            movedNodes.length = 0;
        }

        // 2. Cleanup Classes & Attributes on Form Group
        const pf = getFormGroup();
        if (pf) {
            pf.classList.remove('main-tab', 'super-invisible');
            pf.removeAttribute('data-uniqid');
            pf.style.outline = '';
            pf.title = '';

            const label = pf.querySelector('label');
            if (label) label.classList.remove('super-invisible');
        }

        // 3. Disconnect Observers
        if (mutationObserver) {
            mutationObserver.disconnect();
            mutationObserver = null;
        }
        if (attributeObserver) {
            attributeObserver.disconnect();
            attributeObserver = null;
        }
        
        // 4. Cleanup Listeners
        cleanupListeners();

        // 5. Reset Internal State
        tabs.length = 0;
        // currentTab.value = false;
        isMainTab.value = false;
        mainTab.value = null;

    } finally {
        isRestoring = false;
    }
};

const checkIfFirstSibling = () => {
    let element = getFormGroup();
    if (!element) return false;
    while (element.previousElementSibling) {
        if (element.previousElementSibling.classList.contains('tab-fieldtype')) {
            return false;
        }
        element = element.previousElementSibling;
    }
    return true;
};

const findMainTab = () => {
    let element = getFormGroup();
    if (!element) return null;
    while (element.previousElementSibling) {
        if (element.previousElementSibling.classList.contains('main-tab')) {
            return element.previousElementSibling;
        }
        element = element.previousElementSibling;
    }
    return null;
};

let mutationObserver = null;
let attributeObserver = null;

const initialize = () => {
    if (!rootEl.value) return;
    
    const pf = getFormGroup();
    if (!pf) return;

    isMainTab.value = checkIfFirstSibling();
    
    if (isMainTab.value) {
        pf.classList.add('main-tab');
        pf.dataset.uniqid = tab.id;
        mainTab.value = pf;

        if (!attributeObserver) {
            attributeObserver = new MutationObserver(() => {
                    if (isMainTab.value && (!pf.classList.contains('main-tab') || pf.dataset.uniqid !== tab.id)) {
                    pf.classList.add('main-tab');
                    pf.dataset.uniqid = tab.id;
                }
            });
            attributeObserver.observe(pf, { attributes: true, attributeFilter: ['data-uniqid', 'class'] });
        }

        if (!tabs.find(t => t.id === tab.id)) {
            tabs.push(tab);
            currentTab.value = tab.id;
            events.$on(EVENTS.push(tab.id), (incoming) => {
                const exists = tabs.find(t => t.id === incoming.id);
                if (!exists) {
                    tabs.push(incoming);
                    if (!currentTab.value) currentTab.value = incoming.id;
                }
            });
        }
    } else {
        pf.dataset.uniqid = tab.id;
        const foundMain = findMainTab();
        if (!foundMain || !foundMain.dataset.uniqid) {
            registerTimeout(initialize, 100);
            return;
        }
        mainTab.value = foundMain;
        events.$emit(EVENTS.push(mainTab.value.dataset.uniqid), tab);
        
        // Listen for Reset Signal from Main Tab
        if (releaseEventName) events.$off(releaseEventName);
        releaseEventName = EVENTS.release(mainTab.value.dataset.uniqid);
        events.$on(releaseEventName, () => restoreDom(false));
    }

    registerTimeout(moveInstructions, 0);
};

const moveInstructions = () => {
    const pf = getFormGroup();
    if (!pf) return;
    const instructions = pf.querySelector('.help-block');
    if (instructions) {
        if (isMainTab.value) {
            const activeTabContent = getTabContentEl(tabs[0].id);
            if (activeTabContent) safeMove(instructions, activeTabContent, 'prepend');
            events.$on(EVENTS.prependInstructions(tab.id), (element, id) => {
                const tabContent = getTabContentEl(id);
                if (tabContent) safeMove(element, tabContent, 'prepend');
            });
        } else if (mainTab.value) {
            events.$emit(EVENTS.prependInstructions(mainTab.value.dataset.uniqid), instructions, tab.id);
        }
    }
    registerTimeout(moveSiblings, 0);
};

const moveSiblings = () => {
    const pf = getFormGroup();
    if (!pf) return;
    
    // IDENTIFY OUR CONTAINER
    const myContainer = pf.closest('.contain-paint') || pf.parentElement;

    const nextSiblings = [];
    let element = pf;
    
    while (element.nextElementSibling) {
        const nextEl = element.nextElementSibling;
        
        // HIERARCHY CHECK
        const nextElContainer = nextEl.closest('.contain-paint') || nextEl.parentElement;
        
        if (nextElContainer !== myContainer) {
            break;
        }

        if (nextEl.classList.contains('tab-fieldtype')) {
            break;
        }
            
        // Boundary Check
        if (nextEl.hasAttribute('data-type') || 
            nextEl.classList.contains('bard-set')) {
            break;
        }
        
        nextSiblings.push(nextEl);
        element = nextEl;
    }

    if (isMainTab.value) {
        nextSiblings.forEach(ns => {
            const activeTabContent = getTabContentEl(tabs[0].id);
            if (activeTabContent) {
                const target = activeTabContent.querySelector('.publish-fields');
                if (target) safeMove(ns, target);
            }
        });

        events.$on(EVENTS.append(tab.id), (el, id) => {
            nextTick(() => {
                const tabContent = getTabContentEl(id);
                if (tabContent) {
                    const target = tabContent.querySelector('.publish-fields');
                    if (target) safeMove(el, target);
                }
            });
        });
    } else if (mainTab.value) {
        nextSiblings.forEach(ns => {
            events.$emit(EVENTS.append(mainTab.value.dataset.uniqid), ns, tab.id);
        });
    }
    
    registerTimeout(hideLabels, 0); 
};

const hideLabels = () => {
    const pf = getFormGroup();
    if (!pf) return;
    if (isMainTab.value) {
        const label = pf.querySelector('label');
        if (label) label.classList.add('super-invisible');
    }

    if (!isMainTab.value) {
        pf.classList.add('super-invisible');
    }

    const display = window.getComputedStyle(pf).display;
    tab.hidden = display === 'none';

    if (!mutationObserver) {
        mutationObserver = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    const display = window.getComputedStyle(pf).display;
                    tab.hidden = display === 'none';
                }
            });
        });
        mutationObserver.observe(pf, { attributes: true, attributeFilter: ['style'] });
    }
};

let globalRestoreHandler = null;

const restoreAndInit = (payload) => {
    // Determine IDs
    // Support both old format (string ID) and new format (object payload) for backward compatibility safe
    const originId = typeof payload === 'object' ? payload.originId : payload;
    const incomingBardId = typeof payload === 'object' ? payload.bardId : null;

    if (originId !== tab.id) {
        
        // Scope Check: Match Bard Instance AND Ensure we are in Bard
        const myBard = getBardContainer();
        if (!myBard) return; // Ignore if not in a Bard field

        const myBardId = myBard.dataset.bardInstanceId;
        
        // If both have IDs and they differ, ignore this event (it's for another editor)
        if (incomingBardId && incomingBardId !== myBardId) {
            return;
        }

        // 1. Identify and Freeze Container (Only for Main Tab)
        if (rootEl.value && isMainTab.value) {
            const pf = getFormGroup();
            if (pf) {
                const container = pf.parentElement;
                if (container) {
                    containerRef.value = container;
                    
                    // Freeze height to prevent jump
                    container.style.height = container.offsetHeight + 'px';
                    container.style.overflow = 'hidden';
                    
                    // Ensure relative positioning for overlay
                    if (window.getComputedStyle(container).position === 'static') {
                        container.style.position = 'relative';
                    }

                    isReinitializing.value = true;
                }
            }
        }

        // 2. Restore DOM (Flatten)
        restoreDom(false);
        
        // 3. Re-initialize after delay
        registerTimeout(() => {
            try {
                initialize();
            } catch (e) {
                console.error('[TabFieldtype] Init failed during restore', e);
            } finally {
                 // 4. Cleanup - Ensure this runs even if init fails
                registerTimeout(() => {
                    isReinitializing.value = false;
                    if (containerRef.value) {
                        containerRef.value.style.height = '';
                        containerRef.value.style.overflow = '';
                        containerRef.value.style.position = ''; // Clear forced relative
                        containerRef.value = null;
                    }
                }, 1000); // Reduced delay for snappier feel
            }
        }, 50);
    }
};

onMounted(() => {
    initialize();

    // Listen for Global Restore Signal (from other tabs)
    globalRestoreHandler = restoreAndInit;
    events.$on(EVENTS.globalRestore, globalRestoreHandler);
});


onBeforeUnmount(() => {
    // STOP ALL TIMERS
    clearAllTimers();
    
    // RESTORE DOM (Placeholder Swap + Classes Cleanup)
    restoreDom();

    // REMOVE GLOBAL LISTENER
    if (globalRestoreHandler) {
        events.$off(EVENTS.globalRestore, globalRestoreHandler);
    }
});
</script>
