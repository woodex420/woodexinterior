"""Native PDF text/bounds/pagination proof (requires PyMuPDF, isolated revision-proof fixtures)."""
from pathlib import Path
import json
import re
import pymupdf

root = Path('/tmp/woodex-run/shots/43')
results = []
for count in (1, 5, 10, 14, 16, 18, 22, 45):
    path = root / f'43-furniture-{count}.pdf'
    document = pymupdf.open(path)
    products = []
    closing = []
    for number, page in enumerate(document, 1):
        text = page.get_text()
        assert f'Page {number} of {len(document)}' in text, path
        assert abs(page.rect.width - 595.28) < 1 and abs(page.rect.height - 841.89) < 1
        words = page.get_text('words')
        products.extend(word[4] for word in words if re.fullmatch(r'\d{3}', word[4]))
        assert all(20 <= word[0] and word[2] <= page.rect.width - 20 and 20 <= word[1] and word[3] <= page.rect.height - 8 for word in words), (path, number)
        if page.search_for('Terms and conditions'):
            closing.append(number)
    assert len(products) == count and len(set(products)) == count, (path, products)
    assert closing == [len(document)], (path, closing)
    last = document[-1]
    assert '75% advance payment with work order.' in last.get_text()
    assert '15–20 days' in last.get_text()
    assert len(last.search_for('Marketing Manager')) == 1
    signatures_bottom = last.search_for('Marketing Manager')[0].y1
    assert 775 < signatures_bottom < 800, (path, signatures_bottom)
    amounts_y = last.search_for('Remaining after advance')[0].y0
    terms_y = last.search_for('Terms and conditions')[0].y0
    assert 15 < terms_y - amounts_y < 50, (path, amounts_y, terms_y)
    results.append({'rows': count, 'pages': len(document), 'terms_y_pt': round(terms_y, 2), 'remaining_y_pt': round(amounts_y, 2), 'signatures_bottom_pt': round(signatures_bottom, 2), 'a4_all_words_in_bounds': True})
    if count == 5:
        last.get_pixmap(matrix=pymupdf.Matrix(1.3, 1.3)).save(root / '43-furniture-quotation.png')

for name, token, expected in [('43-oversized-closing', 'note:', 100), ('43-long-single-term', 'Long', 350)]:
    document = pymupdf.open(root / f'{name}.pdf')
    text = ' '.join(page.get_text() for page in document)
    assert len(re.findall(r'\b' + re.escape(token) + (r'\b' if token[-1].isalnum() else ''), text)) == expected, name
    assert 'Marketing Manager' in document[-1].get_text()
    assert 'Final custom condition must remain visible.' in re.sub(r'\s+', ' ', text), name
    if name == '43-long-single-term':
        assert 'END-LONG-TERM' in text
        assert len(re.findall(r'\bapproved\.', text)) == 350
    for number, page in enumerate(document, 1):
        assert f'Page {number} of {len(document)}' in page.get_text()
        assert all(word[3] <= page.rect.height - 8 for word in page.get_text('words')), name
    results.append({'case': name, 'pages': len(document), 'all_custom_text_and_final_signatures_retained': True})

interior = pymupdf.open(root / '43-interior.pdf')
interior[0].get_pixmap(matrix=pymupdf.Matrix(1.3, 1.3)).save(root / '43-interior-quotation.png')
assert 'Interior native block demonstration' in interior[0].get_text()
invoice = pymupdf.open(root / '43-invoice.pdf')
assert '50% advance payment with work order.' in invoice[0].get_text()
assert 'Outstanding balance' in invoice[0].get_text()
results.append({'invoice_legacy_percentage_unchanged': True, 'interior_native_boq_retained': True})
(root / '43-native-pdf-results.json').write_text(json.dumps(results, indent=2) + '\n')
print(json.dumps(results, indent=2))
