import Plugin from 'src/script/helper/plugin/plugin.class';
import PageLoadingIndicatorUtil from 'src/script/utility/loading-indicator/page-loading-indicator.util';
import FormSerializeUtil from 'src/script/utility/form/form-serialize.util';
import HttpClient from 'src/script/service/http-client.service';
import DomAccess from 'src/script/helper/dom-access.helper';

/**
 * This plugin automatically submits a form,
 * when the element or the form itself has changed.
 */
export default class FormAutoSubmitPlugin extends Plugin {

    static options = {
        useAjax: false,
        ajaxContainerSelector: false,
    };

    init() {
        this._getForm();

        if (!this._form) {
            throw new Error(`No form found for the plugin: ${this.constructor.name}`);
        }

        if (this.options.useAjax) {
            if (!this.options.ajaxContainerSelector) {
                throw new Error('The option "ajaxContainerSelector" must ge given when using ajax.');
            }
            this._client = new HttpClient(window.accessKey, window.contextToken);
        }

        this._registerEvents();
    }

    /**
     * tries to get the closest form
     *
     * @returns {HTMLElement|boolean}
     * @private
     */
    _getForm() {
        if (this.el && this.el.nodeName === 'FORM') {
            this._form = this.el;
        } else {
            this._form = this.el.closest('form');
        }
    }

    /**
     * registers all needed events
     *
     * @private
     */
    _registerEvents() {
        if (this.options.useAjax) {
            const onSubmit = this._onSubmit.bind(this);
            this._form.removeEventListener('change', onSubmit);
            this._form.addEventListener('change', onSubmit);
        } else {
            const onChange = this._onChange.bind(this);
            this._form.removeEventListener('change', onChange);
            this._form.addEventListener('change', onChange);
        }
    }

    /**
     * on change callback for the form
     *
     * @private
     */
    _onChange() {
        this._form.submit();
        PageLoadingIndicatorUtil.create();
    }

    /**
     * on submit callback for the form
     *
     * @param event
     *
     * @private
     */
    _onSubmit(event) {
        event.preventDefault();
        PageLoadingIndicatorUtil.create();
        const data = FormSerializeUtil.serialize(this._form);
        const action = DomAccess.getAttribute(this._form, 'action');

        this._client.post(action, data, this._onAfterAjaxSubmit.bind(this));
    }

    /**
     * callback when xhr is finished
     * replaces the container content with the response
     *
     * @param {string} response
     *
     * @private
     */
    _onAfterAjaxSubmit(response) {
        PageLoadingIndicatorUtil.remove();
        const replaceContainer = DomAccess.querySelector(document, this.options.ajaxContainerSelector);
        replaceContainer.innerHTML = response;
        window.PluginManager.initializePlugins();
    }

}
