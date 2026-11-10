# -*- coding: utf-8 -*-
import sys
import io
import json
import re
import sqlite3
import pdfplumber

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

pdf_path = r"E:\ASUS\Desktop\dien-toan-dam-may-va-ung-dung-hubt-2025.pdf"
db_path = r"e:\ASUS\Desktop\edu\database.sqlite"

RED_COLOR_TOLERANCE = lambda c: (
    isinstance(c, (list, tuple)) and len(c) >= 3 and 
    c[0] > 0.6 and c[1] < 0.3 and c[2] < 0.3
)

all_lines = []

with pdfplumber.open(pdf_path) as pdf:
    for page_idx, page in enumerate(pdf.pages):
        words = page.extract_words(extra_attrs=['non_stroking_color'])
        for w in words:
            text = w['text'].strip()
            if not text:
                continue
            color = w.get('non_stroking_color')
            w['is_red'] = RED_COLOR_TOLERANCE(color)

        words.sort(key=lambda x: (round(x['top'] / 5) * 5, x['x0']))

        cur_line = []
        cur_top = None
        for w in words:
            if cur_top is None or abs(w['top'] - cur_top) > 5:
                if cur_line:
                    all_lines.append(cur_line)
                cur_line = [w]
                cur_top = w['top']
            else:
                cur_line.append(w)
        if cur_line:
            all_lines.append(cur_line)

print(f"Total lines extracted: {len(all_lines)}")

# Parse questions from lines
questions = []
current_q = None

def finalize_question(q):
    if not q:
        return
    # Find which option is red
    red_opt = None
    for opt_letter in ['A', 'B', 'C', 'D']:
        if q['opt_red'][opt_letter]:
            red_opt = opt_letter
            break
    if not red_opt:
        red_opt = 'A'
    q['correct'] = red_opt
    questions.append(q)

q_start_regex = re.compile(r'^(?:câu\s*(?:số)?\s*)?(\d+)[\s.:\)\/\-]+(.*)$', re.IGNORECASE)
opt_regex = re.compile(r'^([A-D])[\s.:\)\/\-]+(.*)$')

for line in all_lines:
    line_str = " ".join(w['text'] for w in line).strip()
    if not line_str or line_str == 'EDUQUIZ':
        continue

    # Check if line starts a new question
    m_q = q_start_regex.match(line_str)
    # Check if line starts an option A, B, C, D
    m_opt = opt_regex.match(line_str)

    if m_q and not m_opt:
        # Finalize previous question
        if current_q and (current_q['options']['A'] or current_q['options']['B']):
            finalize_question(current_q)
        
        q_num = int(m_q.group(1))
        q_text = m_q.group(2).strip()
        current_q = {
            'num': q_num,
            'question': q_text,
            'current_opt': None,
            'options': {'A': '', 'B': '', 'C': '', 'D': ''},
            'opt_red': {'A': False, 'B': False, 'C': False, 'D': False}
        }
    elif current_q is not None:
        # Check if line has option markers (could be inline A. ... B. ... or single line)
        # Search for markers [A-D][.:\)]
        tokens = line_str.split(' ')
        # Check if first word is option
        first_word = line[0]['text'].strip()
        m_opt_first = opt_regex.match(first_word) if first_word else None

        if m_opt_first:
            opt_letter = m_opt_first.group(1).upper()
            opt_content = " ".join(w['text'] for w in line[1:]).strip()
            current_q['current_opt'] = opt_letter
            current_q['options'][opt_letter] = opt_content
            if any(w['is_red'] for w in line):
                current_q['opt_red'][opt_letter] = True
        elif current_q['current_opt']:
            # Continuation of current option
            current_q['options'][current_q['current_opt']] += ' ' + line_str
            if any(w['is_red'] for w in line):
                current_q['opt_red'][current_q['current_opt']] = True
        else:
            # Continuation of question text
            current_q['question'] += ' ' + line_str

# Finalize last question
if current_q and (current_q['options']['A'] or current_q['options']['B']):
    finalize_question(current_q)

print(f"Total parsed questions: {len(questions)}")

# Clean up text
def clean_txt(t):
    t = re.sub(r'\bEDUQUIZ\b', '', t, flags=re.I)
    t = t.replace('*', '')
    t = re.sub(r'\s+', ' ', t)
    return t.strip()

for q in questions:
    q['question'] = clean_txt(q['question'])
    for opt in ['A', 'B', 'C', 'D']:
        q['options'][opt] = clean_txt(q['options'][opt])

# Save into SQLite database for exam_id = 8
conn = sqlite3.connect(db_path)
cursor = conn.cursor()

# Delete old questions for exam 8
cursor.execute("DELETE FROM questions WHERE exam_id = 8")

counts = {'A': 0, 'B': 0, 'C': 0, 'D': 0}

for idx, q in enumerate(questions):
    correct = q['correct']
    counts[correct] += 1
    cursor.execute("""
        INSERT INTO questions (exam_id, question_num, question_text, option_a, option_b, option_c, option_d, option_e, correct_answer, explanation, image_url)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    """, (
        8,
        idx + 1,
        f"Câu số {idx + 1}: {q['question']}",
        q['options']['A'],
        q['options']['B'],
        q['options']['C'],
        q['options']['D'],
        '',
        correct,
        '',
        None
    ))

# Update exam question count
cursor.execute("UPDATE exams SET question_count = ? WHERE id = 8", (len(questions),))
conn.commit()
conn.close()

print(f"SUCCESSFULLY UPDATED EXAM 8 IN DATABASE WITH {len(questions)} QUESTIONS!")
print("CORRECT ANSWERS DISTRIBUTION BASED ON RED COLOR:", json.dumps(counts, ensure_ascii=False))

# Show first 5 questions
for q in questions[:5]:
    print(f"\nCâu {q['num']}: {q['question']}")
    print(f" A. {q['options']['A']}")
    print(f" B. {q['options']['B']}")
    print(f" C. {q['options']['C']}")
    print(f" D. {q['options']['D']}")
    print(f" => ĐÁP ÁN ĐÚNG BÔI ĐỎ: {q['correct']}")
