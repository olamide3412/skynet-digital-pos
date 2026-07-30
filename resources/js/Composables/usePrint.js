export function usePrint() {
    function printElement(selector = '.receipt-print-area') {
        window.print()
    }

    return { printElement }
}
