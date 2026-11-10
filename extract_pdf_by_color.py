# -*- coding: utf-8 -*-
import sys
import io
import json
import re
import pdfplumber

# Set stdout UTF-8
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

pdf_path = r"E:\ASUS\Desktop\dien-toan-dam-may-va-ung-dung-hubt-2025.pdf"

RED_COLOR_TOLERANCE = lambda c: (
    isinstance(c, (list, tuple)) and len(c) >= 3 and 
    c[0] > 0.6 and c[1] < 0.3 and c[2] < 0.3
)

questions = []
current_q = None
current_opt = None

with pdfplumber.open(pdf_path) as pdf:
    for page_idx, page in enumerate(pdf.pages):
        # Extract words with their colors
        words = page.extract_words(extra_attrs=['non_stroking_color'])
        for w in words:
            text = w['text'].strip()
            if not text:
                continue
            color = w.get('non_stroking_color')
            is_red = RED_COLOR_TOLERANCE(color)
            w['is_red'] = is_red

        # Sort words top-to-bottom, left-to-right
        words.sort(key=lambda x: (round(x['top'] / 5) * 5, x['x0']))

        # Line reconstruction
        lines = []
        cur_line = []
        cur_top = None
        for w in words:
            if cur_top is None or abs(w['top'] - cur_top) > 5:
                if cur_line:
                    lines.append(cur_line)
                cur_line = [w]
                cur_top = w['top']
            else:
                cur_line.append(w)
        if cur_line:
            lines.append(cur_line)

        # Print debug of first 3 questions
        for line in lines:
            line_str = " ".join(w['text'] for w in line)
            line_has_red = any(w['is_red'] for w in line)
            red_words = [w['text'] for w in line if w['is_red']]
            if line_has_red and len(questions) < 10:
                print(f"[P{page_idx+1}] RED FOUND: {red_words} in line: {line_str[:80]}")

print("ANALYSIS COMPLETED SUCCESSFULLY.")
