module.exports = {
    '@tags': ['tax-create', 'tax', 'create'],
    'open tax module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/tax/index', 'Tax');
    },
    'goto create tax page': (browser) => {
        browser
            .click('a[href="#/sw/settings/tax/create"]')
            .waitForElementVisible('.sw-settings-tax-detail .sw-page__content')
            .assert.urlContains('#/sw/settings/tax/create')
            .assert.containsText('.sw-card__title', 'Settings')
            .fillField('input[name=sw-field--tax-name]', 'High tax')
            .fillField('input[name=sw-field--tax-taxRate]', '99')
            .click('.sw-settings-tax-detail__save-action')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert__message', 'Tax "High tax" has been saved successfully.')
            .assert.urlContains('#/sw/settings/tax/detail');
    },
    'go back to listing': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-settings-tax-list-grid')
            .click('.sw-alert button.sw-alert__close')
            .waitForElementNotPresent('.sw-alert__message');
    },
    'delete tax': (browser) => {
        browser
            .assert.containsText('.sw-grid-row:last-child .sw-tax-list__column-name', 'High tax')
            .click('.sw-grid-row:last-child .sw-context-button__button')
            .waitForElementPresent('body > .sw-context-menu')
            .click('body > .sw-context-menu .sw-context-menu-item--danger')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-modal__body', 'Are you sure you want to delete the tax "High tax"?')
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-notifications .sw-alert', 'Tax "High tax" has been deleted successfully.');
    },
    after: (browser) => {
        browser.end();
    }
};