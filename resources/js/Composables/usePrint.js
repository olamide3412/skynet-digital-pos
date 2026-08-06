export function usePrint() {
    function printElement(selector = '.receipt-print-area', options = {}) {
        const el = document.querySelector(selector)
        if (!el) { window.print(); return }

        const paperSize = options.paperSize || '80mm'

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

        const printWindow = window.open('', '_blank', 'width=850,height=900')

        const isA4 = paperSize === 'A4'

        const pageStyles = isA4 ? `
            @page {
                margin: 12mm;
                size: A4 portrait;
            }
            body {
                font-family: 'Segoe UI', system-ui, Arial, sans-serif;
                font-size: 13px;
                font-weight: 500;
                line-height: 1.5;
                color: #000000 !important;
                background: #ffffff !important;
                width: 100%;
                max-width: 190mm;
                margin: 0 auto;
                padding: 10px;
            }
        ` : `
            @page {
                margin: 0mm;
                size: 80mm auto;
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
        `

        printWindow.document.write(`
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt / Invoice</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }
        ${pageStyles}
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; color: #000000 !important; }
        svg { display: block; margin: 0 auto; max-width: 100%; }
        div { box-sizing: border-box; }
        div[style*="display:flex"] { display: flex !important; justify-content: center !important; align-items: center !important; }
        @media print {
            html, body {
                background: #fff !important;
                color: #000 !important;
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
