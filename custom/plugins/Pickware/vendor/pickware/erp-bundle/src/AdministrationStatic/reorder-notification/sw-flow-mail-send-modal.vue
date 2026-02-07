<template>
    {% block sw_flow_mail_send_modal_document_types %}
    <template
        v-if="shouldEnableDocumentAttachments"
    >
        {% parent %}
    </template>
    {% endblock %}

    {% block sw_flow_mail_send_modal_document_warning %}
    <template
        v-if="shouldEnableDocumentAttachments"
    >
        {% parent %}
    </template>
    {% endblock %}
</template>

<script>
export const ReorderMailTemplateTypeTechnicalName = 'pickware_erp_reorder';

export default {
    overrideFrom: 'sw-flow-mail-send-modal',

    computed: {
        shouldEnableDocumentAttachments() {
            const currentMailTemplate = this.mailTemplates.find((item) => item.id === this.mailTemplateId);

            return !currentMailTemplate
                || currentMailTemplate.mailTemplateType.technicalName !== ReorderMailTemplateTypeTechnicalName;
        },
    },

    methods: {
        onChangeMailTemplate(...args) {
            this.$super('onChangeMailTemplate', ...args);

            if (!this.shouldEnableDocumentAttachments) {
                this.documentTypeIds = [];
            }
        },
    },
};
</script>
