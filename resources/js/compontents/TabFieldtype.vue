<template>
    <div>
        <div class="tabs-container relative" v-if="isMainTab">
            <div role="tablist" class="tabs flex-1 flex space-x-3 overflow-auto pr-6">
                <button v-for="tab, index in tabs" role="tab" class="tab-button" :class="{ active: tab.active, hidden: tab.hidden }" @click="setTabActive(index)">
                    <iconify-icon v-if="tab.iconify_icon" :icon="tab.iconify_icon" class="h-4 w-4 text-lg mr-2" />
                    <svg-icon v-else-if="tab.icon" :name="tab.icon" class="h-4 w-4 mr-2" />
                    {{ tab.name }}
                </button>
            </div>
        </div>

        <div role="tabpanel" v-for="tab, index in tabs" class="tabpanel" :class="{ block: tab.active, hidden: !tab.active }" :id="'tab-content-' + tab.id">
            <div :style="tabPanelStyle">
                <div class="publish-fields @container w-full"></div>
            </div>
        </div>
    </div>
</template>

<script>
import uniqid from 'uniqid';

export default {

    mixins: [Fieldtype],

    data() {
        return {
            isMainTab: false,
            mainTab: null,
            padding: 0,
            tab: {
                id: uniqid(),
                handle: this.config.handle,
                name: this.config.display,
                icon: this.config.tab_icon,
                iconify_icon: this.config.tab_iconify_icon,
                active: false,
                hidden: false
            },
            tabs: [],
        };
    },

    computed: {
        tabPanelStyle() {
            return {
                marginLeft: '-' + this.padding + 'px',
                marginRight: '-' + this.padding + 'px',
            }
        }
    },

    methods: {
        setTabActive(index) {
            this.tabs.forEach((tab, i) => {
                tab.active = i === index
            })
        },
        checkIfFirstSibling() {
            let element = this.$el.closest('.publish-field')
            let previousSiblings = []

            while (element.previousElementSibling) {
                if (element.previousElementSibling.classList.contains('tab-fieldtype')) {
                    return false
                }
                element = element.previousElementSibling
            }

            return true
        },
        findMainTab() {
            let element = this.$el.closest('.publish-field')
            let previousSiblings = []

            while (element.previousElementSibling) {
                if (element.previousElementSibling.classList.contains('main-tab')) {
                    return element.previousElementSibling
                }
                element = element.previousElementSibling
            }

            return null
        },
        calculatePadding() {
            let paddedElement = this.$el.querySelector('.tabpanel.block .publish-field')

            if (paddedElement) {
                const style = window.getComputedStyle(paddedElement)
                const padding = style.getPropertyValue('padding-left')
                const paddingInt = parseInt(padding, 10)
                this.padding = paddingInt
            }
        }
    },

    mounted() {

        this.isMainTab = this.checkIfFirstSibling()

        /** Setup main tab */
        if (this.isMainTab) {
            this.$el.closest('.publish-field').classList.add('main-tab')
            this.$el.closest('.publish-field').dataset.uniqid = this.tab.id

            this.mainTab = this.$el.closest('.publish-field')
        } else {
            this.mainTab = this.findMainTab()
        }

        /** Setup events to push the tabs to the main Tab component */
        if (this.isMainTab) {
            this.tab.active = true
            this.tabs.push(this.tab)

            this.$events.$on('tabs.push-' + this.tab.id, (tab) => {
                this.tabs.push(tab)
            })
        } else {
            this.$events.$emit('tabs.push-' + this.mainTab.dataset.uniqid, this.tab)
        }

        /** Move the tab field instructions to the right place */
        this.$nextTick(() => {
            const instructions = this.$el.closest('.publish-field').querySelector('.help-block')
            if (instructions) {
                if (this.isMainTab) {
                    document.getElementById('tab-content-' + this.tabs[0].id).prepend(instructions)
                    this.$events.$on('tabs.prepend-instructions-' + this.tab.id, (element, id) => {
                        document.getElementById('tab-content-' + id).prepend(element)
                    })
                } else {
                    this.$events.$emit('tabs.prepend-instructions-' + this.mainTab.dataset.uniqid, instructions, this.tab.id)
                }
            }
        })

        /** Find all next siblings that are fields, but stop if you encounter an other tab */
        let element = this.$el.closest('.publish-field')
        let nextSiblings = [];

        while (element.nextElementSibling) {
            if (element.nextElementSibling.classList.contains('tab-fieldtype')) {
                break
            }

            nextSiblings.push(element.nextElementSibling);
            element = element.nextElementSibling;
        }

        /** Append the sibling fields to the correct tab content container */
        this.$nextTick(() => {
            if (this.isMainTab) {
                nextSiblings.forEach((nextSibling) => {
                    document.getElementById('tab-content-' + this.tabs[0].id).querySelector('.publish-fields').appendChild(nextSibling)
                })
    
                this.$events.$on('tabs.append-' + this.tab.id, (element, id) => {
                    document.getElementById('tab-content-' + id).querySelector('.publish-fields').appendChild(element)
                })
            } else {
                nextSiblings.forEach((nextSibling) => {
                    this.$events.$emit('tabs.append-' + this.mainTab.dataset.uniqid, nextSibling, this.tab.id)
                })
            }
        })

        // Hide the label from the main tab
        if (this.isMainTab) {
            this.$el.closest('.publish-field').querySelector('label').classList.add('super-invisible')
        }

        // Make the now empty publish field invisible without touching the display property, so that we can track it with a MutationObserver
        if (!this.isMainTab) {
            this.$el.closest('.publish-field').classList.add('super-invisible')
        }

        this.$nextTick(() => {
            if (this.isMainTab) {
                this.calculatePadding()

                window.addEventListener('resize', event => {
                    this.calculatePadding()
                })
            }
        })

        this.$nextTick(() => {
            const publishField = this.$el.closest('.publish-field')
            const display = window.getComputedStyle(publishField).display
            if (display === 'none') {
                this.tab.hidden = true
            } else {
                this.tab.hidden = false
            }
        })
    },

    created() {
        // observe the tab publish field and its display property
        this.$nextTick(() => {
            const publishField = this.$el.closest('.publish-field')

            const observer = new MutationObserver(mutations => {
                mutations.forEach(mutation => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                        const display = window.getComputedStyle(publishField).display
                        if (display === 'none') {
                            this.tab.hidden = true
                        } else {
                            this.tab.hidden = false
                        }
                    }
                });
            });

            observer.observe(publishField, { attributes: true, attributeFilter: ['style'] })
        });
    },
};
</script>