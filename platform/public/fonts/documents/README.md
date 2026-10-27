# Quotation document fonts

DM Sans 400/500/700 come from the owner's v2.1 Pro `assets/fonts/dm-sans-*.woff2`.
The WOFF2 containers were losslessly decompressed to TTF with FontTools so the same
font outlines work in Chromium and dompdf (which does not read WOFF2). No font
outlines or names were edited. SIL OFL 1.1 is included in `OFL.txt`; copyright:
The DM Sans Project Authors (https://github.com/googlefonts/dm-fonts).

These embedded font copies are for native Sales documents. The public v1 website is unchanged.

## Furniture quotation revision 3

Inter 400/500/700 reuse the existing public `assets/fonts/inter-*.woff2` files from
this repository. FontTools losslessly decompresses their WOFF2 containers to TTF;
no outlines, names or commercial document values are changed. Chromium preview
embeds the TTF bytes and dompdf reads those same local files, with no Google Fonts
or other CDN request. `Inter-OFL.txt` contains the SIL OFL 1.1 licence and the
embedded copyright: 2016 The Inter Project Authors (https://github.com/rsms/inter).

Rebuild these containers with FontTools and Brotli installed:

```python
from fontTools.ttLib import TTFont
for weight in (400, 500, 700):
    font = TTFont(f"platform/public/assets/fonts/inter-{weight}.woff2")
    font.flavor = None
    font.save(f"platform/public/fonts/documents/inter-{weight}.ttf")
```

Old quotation revisions and Furniture invoices retain their existing DM Sans
renderer. The public-v1 font files are unchanged.
