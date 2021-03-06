import { Mixin } from 'src/core/shopware';
import domUtils from 'src/core/service/utils/dom.utils';
import './sw-field-copyable.scss';
import template from './sw-field-copyable.html.twig';

/**
 * @private
 */
export default {
    name: 'sw-field-copyable',
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        copyableText: {
            type: String,
            required: false
        },

        tooltip: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            wasCopied: false
        };
    },

    computed: {
        tooltipText() {
            if (this.wasCopied) {
                return this.$tc('global.sw-field-copyable.tooltip.wasCopied');
            }

            return this.$tc('global.sw-field-copyable.tooltip.canCopy');
        }
    },

    methods: {
        copyToClipboard() {
            if (!this.copyableText) {
                return;
            }

            try {
                domUtils.copyToClipboard(this.copyableText);
                if (this.tooltip) {
                    this.tooltipSuccess();
                } else {
                    this.notificationSuccess();
                }
            } catch (err) {
                this.createNotificationError({
                    title: this.$tc('global.sw-field.notification.notificationCopyFailureTitle'),
                    message: this.$tc('global.sw-field.notification.notificationCopyFailureMessage')
                });
            }
        },

        tooltipSuccess() {
            this.wasCopied = true;
        },

        notificationSuccess() {
            this.createNotificationInfo({
                title: this.$tc('global.sw-field.notification.notificationCopySuccessTitle'),
                message: this.$tc('global.sw-field.notification.notificationCopySuccessMessage')
            });
        },

        resetTooltipText() {
            this.wasCopied = false;
        }
    }
};
