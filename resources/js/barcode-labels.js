/**
 * Render barcode label produk untuk dicetak & ditempel di kemasan.
 *
 * Halaman label owner (owner/products/labels) mengirim daftar produk
 * sebagai <div data-barcode="..."> kosong; modul ini menggambar barcode
 * Code128 ke masing-masing elemen.
 *
 * Code128 dipilih (bukan EAN-13) karena barcode produk ini dibuat sendiri
 * oleh toko — Code128 menerima karakter apa pun dan tidak menuntut
 * panjang 13 digit + check digit.
 */
import JsBarcode from 'jsbarcode';

function renderBarcode(element) {
    const value = (element.dataset.barcode ?? '').trim();

    if (value === '') return;

    try {
        JsBarcode(element, value, {
            format: 'CODE128',
            width: 2,
            height: 52,
            margin: 0,
            displayValue: true,
            fontSize: 13,
            font: 'monospace',
            textMargin: 2,
            lineColor: '#000000',
            background: '#ffffff',
        });
    } catch {
        // Barcode tidak valid untuk Code128 — tampilkan teksnya saja
        // supaya owner masih bisa melihat kodenya saat print.
        element.textContent = value;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-barcode]').forEach(renderBarcode);
});
