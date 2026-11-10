# -*- coding: utf-8 -*-
import sys
import json
import pdfplumber

pdf_path = r"E:\ASUS\Desktop\dien-toan-dam-may-va-ung-dung-hubt-2025.pdf"

colors_found = set()
red_samples = []

with pdfplumber.open(pdf_path) as pdf:
    print(f"Total pages: {len(pdf.pages)}")
    for page_idx, page in enumerate(pdf.pages):
        for c in page.chars:
            txt = c['text'].strip()
            if not txt:
                continue
            color = c.get('non_stroking_color')
            # normalize color
            c_str = str(color)
            colors_found.add(c_str)

            # Check if color is red or non-black
            # Black is typically 0, (0,0,0), [0,0,0], (0,), or None
            is_black = color in [0, (0,), (0, 0, 0), [0, 0, 0], (0.0, 0.0, 0.0), None]
            if not is_black:
                red_samples.append({
                    'page': page_idx + 1,
                    'text': txt,
                    'color': c_str,
                    'top': round(c['top'], 1),
                    'x0': round(c['x0'], 1)
                })

print("COLORS FOUND ACROSS PDF:")
for clr in colors_found:
    print(" -", clr)

print(f"\nTOTAL NON-BLACK CHARACTERS: {len(red_samples)}")
if red_samples:
    print("\nSAMPLE NON-BLACK WORDS (first 100 chars):")
    non_black_text = "".join(x['text'] for x in red_samples[:150])
    print(non_black_text)
