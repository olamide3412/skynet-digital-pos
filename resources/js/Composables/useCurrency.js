/**
 * useCurrency — formats a number as ₦1,250.00
 */
export function useCurrency(symbol = '₦') {
    function format(value) {
        const num = parseFloat(value) || 0
        return symbol + num.toLocaleString('en-NG', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })
    }

    return { format }
}
