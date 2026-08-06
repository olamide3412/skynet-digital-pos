export function usePrint() {
    function printElement(selector = '.receipt-print-area') {
        const el = document.querySelector(selector)
        if (!el) { window.print(); return }

        // Clone the element so we can serialise embedded SVGs (barcode) correctly
        const clone = el.cloneNode(true)

        // Inline any SVG elements (JsBarcode renders into <svg> in the DOM)
        const srcSvgs   = el.querySelectorAll('svg')
        const cloneSvgs = clone.querySelectorAll('svg')
        srcSvgs.forEach((svg, i) => {
            if (cloneSvgs[i]) {
                cloneSvgs[i].outerHTML = svg.outerHTML
            }
        })

        const content = clone.innerHTML

        const printWindow = window.open('', '_blank', 'width=420,height=700')
        printWindow.document.write(`
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }
        body {
            font-family: 'Consolas', 'Lucida Console', 'Segoe UI', Arial, 'Courier New', monospace, sans-serif;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.4;
            color: #000000 !important;
            background: #ffffff !important;
            width: 72mm;
            margin: 0 auto;
            padding: 4mm 2mm 10mm;
        }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; color: #000000 !important; }
        svg { display: block; margin: 0 auto; max-width: 100%; }
        div { box-sizing: border-box; }
        div[style*="display:flex"] { display: flex !important; justify-content: center !important; align-items: center !important; }
        @media print {
            @page {
                margin: 0mm;
                size: 80mm auto;
            }
            html, body {
                width: 100%;
                margin: 0;
                padding: 2mm 3mm;
                background: #fff;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
${content}
</body>
</html>`)
        printWindow.document.close()
        printWindow.focus()
        setTimeout(() => {
            printWindow.print()
            printWindow.close()
        }, 400)
    }

    return { printElement }
}
