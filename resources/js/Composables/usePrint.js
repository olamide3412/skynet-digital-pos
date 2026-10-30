export function usePrint() {
    function isMobileDevice() {
        if (typeof window === 'undefined' || typeof navigator === 'undefined') return false
        const ua = navigator.userAgent || ''
        const isMobileUA = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|mobile|CriOS/i.test(ua)
        const hasTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0)
        const isSmallScreen = window.innerWidth <= 1024
        return isMobileUA || (hasTouch && isSmallScreen)
    }

    function printDirectToWindow(fullHtml, pageStyles) {
        // Clean up any existing direct print elements
        const oldContainer = document.getElementById('pos-direct-print-container')
        if (oldContainer) oldContainer.remove()
        const oldStyle = document.getElementById('pos-direct-print-style')
        if (oldStyle) oldStyle.remove()

        // 1. Create a dedicated container attached directly to body
        const container = document.createElement('div')
        container.id = 'pos-direct-print-container'
        container.innerHTML = fullHtml
        document.body.appendChild(container)

        // 2. Inject print stylesheet targeting only the direct print container
        const styleEl = document.createElement('style')
        styleEl.id = 'pos-direct-print-style'
        styleEl.textContent = `
            #pos-direct-print-container {
                display: none !important;
            }
            @media print {
                html, body {
                    background: #ffffff !important;
                    color: #000000 !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    width: 100% !important;
                    height: auto !important;
                    overflow: visible !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                body > *:not(#pos-direct-print-container) {
                    display: none !important;
                }
                #pos-direct-print-container {
                    display: block !important;
                    position: static !important;
                    width: 100% !important;
                    margin: 0 auto !important;
                    padding: 0 !important;
                    background: #ffffff !important;
                    color: #000000 !important;
                    visibility: visible !important;
                    z-index: 99999999 !important;
                }
                #pos-direct-print-container * {
                    visibility: visible !important;
                    color: #000000 !important;
                }
                table { width: 100%; border-collapse: collapse; }
                td, th { vertical-align: top; color: #000000 !important; }
                svg { display: block; margin: 0 auto; max-width: 100%; }
                .no-print {
                    display: none !important;
                }
                ${pageStyles}
            }
        `
        document.head.appendChild(styleEl)

        const cleanup = () => {
            if (container.parentNode) container.remove()
            if (styleEl.parentNode) styleEl.remove()
            window.removeEventListener('afterprint', cleanup)
        }
        window.addEventListener('afterprint', cleanup)

        setTimeout(() => {
            try {
                window.focus()
                window.print()
            } catch (err) {
                console.error('Window print failed:', err)
            }
            setTimeout(cleanup, 3000)
        }, 150)
    }

    async function printElement(selector = '.receipt-print-area', options = {}) {
        const el = document.querySelector(selector)
        if (!el) { window.print(); return }

        const paperSize = options.paperSize || '80mm'
        const printerConnection = options.printerConnection || 'kiosk_direct'
        const localAgentUrl = options.localAgentUrl || 'http://127.0.0.1:8080/print'

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

        // If local agent endpoint is configured, attempt sending payload
        if (printerConnection === 'local_agent') {
            try {
                const response = await fetch(localAgentUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        html: content,
                        paper_size: paperSize,
                        printer_name: options.printerName,
                        ip_address: options.printerIpAddress,
                    }),
                })
                if (response.ok) {
                    return true
                }
            } catch (err) {
                console.warn('Local print agent unreachable, falling back to direct print:', err)
            }
        }

        const isA4 = paperSize === 'A4'
        const copies = Math.max(1, parseInt(options.copies) || 1)

        let fullHtml = ''
        for (let i = 0; i < copies; i++) {
            const isLast = i === copies - 1
            const pageBreakStyle = isLast ? '' : 'page-break-after: always; break-after: page;'
            fullHtml += `<div class="receipt-single-copy" style="${pageBreakStyle}">${content}</div>`
        }

        const pageStyles = isA4 ? `
            @page {
                margin: 10mm 15mm;
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
                max-width: 180mm;
                margin: 0 auto;
                padding: 15px;
            }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 4px 2px; }
            .receipt-print-area, .laundry-ticket-print-area { max-height: none !important; overflow: visible !important; }
            .receipt-single-copy { page-break-inside: avoid; break-inside: avoid; }
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
            table { width: 100%; border-collapse: collapse; }
            .receipt-print-area, .laundry-ticket-print-area { max-height: none !important; overflow: visible !important; }
            .receipt-single-copy { page-break-inside: avoid; break-inside: avoid; }
        `

        // Mobile browsers (iOS Safari, Android Chrome) block or silently ignore hidden 0x0 iframes.
        // Use direct window printing with isolated print container on mobile devices.
        if (isMobileDevice()) {
            printDirectToWindow(fullHtml, pageStyles)
            return true
        }

        // On desktop, use a hidden iframe for silent background printing
        let iframe = document.getElementById('pos-receipt-print-frame')
        if (!iframe) {
            iframe = document.createElement('iframe')
            iframe.id = 'pos-receipt-print-frame'
            iframe.style.position = 'fixed'
            iframe.style.right = '0'
            iframe.style.bottom = '0'
            iframe.style.width = '0'
            iframe.style.height = '0'
            iframe.style.border = '0'
            document.body.appendChild(iframe)
        }

        const doc = iframe.contentWindow.document
        doc.open()
        doc.write(`
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
${fullHtml}
</body>
</html>`)
        doc.close()

        setTimeout(() => {
            try {
                iframe.contentWindow.focus()
                iframe.contentWindow.print()
            } catch (e) {
                console.error('Direct iframe print failed, falling back to window print container:', e)
                printDirectToWindow(fullHtml, pageStyles)
            }
        }, 250)
    }

    return { printElement }
}
