import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Composable for currency formatting using the active WHMCS currency.
 */
export function useCurrency() {
    const page = usePage();

    const activeCurrency = computed(() => {
        const currencies = page.props.currencies || [];
        const activeId = page.props.activeCurrencyId || 1;
        return currencies.find(c => c.id == activeId) || currencies[0] || null;
    });

    const currencyPrefix = computed(() => activeCurrency.value?.prefix || '$');
    const currencySuffix = computed(() => activeCurrency.value?.suffix || '');
    const currencyCode = computed(() => activeCurrency.value?.code || 'USD');

    /**
     * Format an amount with the active currency's prefix/suffix.
     * @param {string|number} amount
     * @returns {string}
     */
    function formatCurrency(amount) {
        const val = parseFloat(amount) || 0;
        const formatted = val.toFixed(2);
        const prefix = currencyPrefix.value;
        const suffix = currencySuffix.value;
        return `${prefix}${formatted}${suffix ? ' ' + suffix : ''}`;
    }

    return {
        activeCurrency,
        currencyPrefix,
        currencySuffix,
        currencyCode,
        formatCurrency,
    };
}
