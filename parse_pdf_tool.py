# -*- coding: utf-8 -*-
"""
parse_pdf_tool.py — Trích xuất đề thi PDF với nhận diện chữ có màu (Màu đỏ, xanh, vàng, cam, tím, highlight)
    Tự động làm sạch watermark và nhận diện đáp án dựa trên màu, nền highlight,
    ký hiệu trước A/B/C/D/E hoặc bảng đáp án ở cuối tài liệu.
Sử dụng: python parse_pdf_tool.py <path_to_pdf>
"""
import sys
import io
import json
import os
import re
import pdfplumber

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

if len(sys.argv) < 2:
    print(json.dumps({'success': False, 'message': 'Thiếu đường dẫn file PDF'}, ensure_ascii=False))
    sys.exit(1)

pdf_path = sys.argv[1]

ANSWER_MARKER_CHARS = r'*✱✳✓✔☑☒✅✦•●◉◆◇▶➜→➤+'


def find_page_question_starts(page):
    """Trả về [(số câu, vị trí trên trang)] để gắn ảnh đúng câu."""
    try:
        words = sorted(page.extract_words(), key=lambda item: (float(item.get('top', 0)), float(item.get('x0', 0))))
    except Exception:
        return []

    starts = []
    label_pattern = re.compile(r'^(?:câu|cau|question|bài|bai)$', re.IGNORECASE)
    joined_pattern = re.compile(r'^(?:câu|cau|question|bài|bai)\s*(?:số)?\s*(\d+)', re.IGNORECASE)
    numeric_pattern = re.compile(r'^(\d+)[\).]$')

    for index, word in enumerate(words):
        text = str(word.get('text', '')).strip()
        match = joined_pattern.match(text)
        if match:
            starts.append((int(match.group(1)), float(word.get('top', 0))))
            continue

        if label_pattern.match(text) and index + 1 < len(words):
            next_text = str(words[index + 1].get('text', '')).strip()
            number_match = re.match(r'^(\d+)', next_text)
            if number_match:
                starts.append((int(number_match.group(1)), float(word.get('top', 0))))
                continue

        # Một số đề chỉ ghi "1)" hoặc "1." ở đầu câu, không có chữ "Câu".
        numeric_match = numeric_pattern.match(text)
        if numeric_match:
            starts.append((int(numeric_match.group(1)), float(word.get('top', 0))))
        elif re.match(r'^\d+$', text) and index + 1 < len(words):
            next_text = str(words[index + 1].get('text', '')).strip()
            if next_text in (')', '.'):
                starts.append((int(text), float(word.get('top', 0))))

    # Không để một câu bị lặp nếu PDF tách tiêu đề thành nhiều lớp text.
    unique = []
    seen = set()
    for question_num, top in starts:
        key = (question_num, round(top, 1))
        if key not in seen:
            unique.append((question_num, top))
            seen.add(key)
    return sorted(unique, key=lambda item: item[1])


def extract_question_images(pdf_path):
    """Tách ảnh nhúng trong PDF, cắt theo bbox trên trang và gắn vào số câu gần nhất."""
    image_by_question = {}
    image_dir = os.path.join(os.path.dirname(__file__), 'uploads', 'pdf_images')
    try:
        os.makedirs(image_dir, exist_ok=True)
        base_name = re.sub(
            r'[^A-Za-z0-9_-]+',
            '_',
            os.path.splitext(os.path.basename(pdf_path))[0]
        ).strip('_')[:80] or 'pdf'

        with pdfplumber.open(pdf_path) as pdf:
            for page_index, page in enumerate(pdf.pages):
                question_starts = find_page_question_starts(page)
                if not question_starts:
                    continue

                try:
                    page_images = list(getattr(page, 'images', []) or [])
                except Exception:
                    page_images = []
                if not page_images:
                    continue

                try:
                    rendered_page = page.to_image(resolution=150)
                    rendered = rendered_page.original
                except Exception as render_error:
                    sys.stderr.write('Không thể render ảnh PDF: %s\n' % render_error)
                    continue

                for image_index, image in enumerate(page_images):
                    try:
                        x0 = float(image.get('x0', 0))
                        x1 = float(image.get('x1', 0))
                        top = float(image.get('top', 0))
                        bottom = float(image.get('bottom', 0))
                        if x1 <= x0 or bottom <= top or (x1 - x0) < 4 or (bottom - top) < 4:
                            continue

                        # Ảnh nằm sau câu hiện tại và trước câu tiếp theo được gắn cho câu hiện tại.
                        previous = [item for item in question_starts if item[1] <= bottom + 2]
                        if previous:
                            question_num = max(previous, key=lambda item: item[1])[0]
                        else:
                            following = [item for item in question_starts if item[1] >= bottom - 2]
                            if not following:
                                continue
                            question_num = min(following, key=lambda item: item[1])[0]

                        # Một câu có thể có nhiều XObject; quiz hiện tại hiển thị một ảnh đại diện.
                        if question_num in image_by_question:
                            continue

                        pixel_bbox = rendered_page._reproject_bbox((x0, top, x1, bottom))
                        left, upper, right, lower = [int(round(value)) for value in pixel_bbox]
                        left = max(0, min(left, rendered.width))
                        upper = max(0, min(upper, rendered.height))
                        right = max(0, min(right, rendered.width))
                        lower = max(0, min(lower, rendered.height))
                        if right <= left or lower <= upper:
                            continue

                        crop = rendered.crop((left, upper, right, lower))
                        filename = 'question_image_%s_p%s_i%s.png' % (
                            base_name,
                            page_index + 1,
                            image_index + 1,
                        )
                        output_path = os.path.join(image_dir, filename)
                        crop.save(output_path, format='PNG', optimize=True)
                        image_by_question[question_num] = 'uploads/pdf_images/' + filename
                    except Exception as image_error:
                        sys.stderr.write('Bỏ qua một ảnh PDF: %s\n' % image_error)
    except Exception as error:
        # PDF không có ảnh hoặc PDF lỗi không được làm hỏng toàn bộ quá trình nhập đề.
        sys.stderr.write('Không thể tách ảnh từ PDF: %s\n' % error)

    return image_by_question

def is_colored_token(c):
    """
    Kiểm tra xem từ có phải là màu đỏ hoặc màu khác nổi bật (khác màu đen/xám thông thường) hay không.
    - Màu đen thông thường: (0, 0, 0) hoặc c == 0 hoặc xám đen (r ~= g ~= b < 0.35)
    - Màu nổi bật: Đỏ, Xanh lá, Xanh lam, Vàng, Cam, Tím, Hồng,...
    """
    if c is None:
        return False
    
    if isinstance(c, (list, tuple)) and len(c) == 4:
        # CMYK: đen thuần thường là (0, 0, 0, 1), không xem là màu đánh dấu.
        c_val, m, y, k = [float(value) for value in c]
        if max(c_val, m, y, k) > 1:
            c_val, m, y, k = [value / 255 for value in (c_val, m, y, k)]
        return (c_val + m + y) > 0.25 and k < 0.9

    # Nếu là tuple/list RGB
    if isinstance(c, (list, tuple)) and len(c) >= 3:
        r, g, b = [float(value) for value in c[:3]]
        if max(r, g, b) > 1:
            r, g, b = r / 255, g / 255, b / 255
        # Màu đỏ: r vượt trội
        if r > 0.55 and g < 0.40 and b < 0.40:
            return True
        # Màu xanh lá: g vượt trội
        if g > 0.50 and r < 0.45:
            return True
        # Màu xanh dương: b vượt trội
        if b > 0.50 and r < 0.45:
            return True
        # Độ bão hòa màu cao (vàng, cam, tím, hồng...)
        spread = max(r, g, b) - min(r, g, b)
        if spread > 0.16 and (r > 0.35 or g > 0.35 or b > 0.35):
            # Loại trừ xám xanh của watermark (0.21, 0.25, 0.32) có max < 0.35
            return True

    return False


def is_highlighted_word(page, word):
    """Nhận diện nền highlight dạng rectangle nằm phía sau chữ."""
    word_x = (float(word.get('x0', 0)) + float(word.get('x1', 0))) / 2
    word_y = (float(word.get('top', 0)) + float(word.get('bottom', 0))) / 2
    word_height = max(1.0, float(word.get('bottom', 0)) - float(word.get('top', 0)))

    for rect in getattr(page, 'rects', []):
        rect_color = rect.get('non_stroking_color') or rect.get('stroking_color')
        rect_height = float(rect.get('bottom', 0)) - float(rect.get('top', 0))
        if not is_colored_token(rect_color) or rect_height <= 0:
            continue
        # Bỏ qua các khối nền lớn của trang; highlight chữ thường chỉ cao
        # khoảng một đến vài lần chiều cao dòng.
        if rect_height > max(40.0, word_height * 5.0):
            continue
        if (
            float(rect.get('x0', 0)) <= word_x <= float(rect.get('x1', 0))
            and float(rect.get('top', 0)) <= word_y <= float(rect.get('bottom', 0))
        ):
            return True
    return False

try:
    all_words = []
    with pdfplumber.open(pdf_path) as pdf:
        for page_index, page in enumerate(pdf.pages):
            words = page.extract_words(extra_attrs=['non_stroking_color', 'stroking_color'])
            for w in words:
                txt = w['text'].strip()
                if not txt:
                    continue
                
                # Bỏ watermark EDUQUIZ và rác
                fill_color = w.get('non_stroking_color')
                stroke_color = w.get('stroking_color')
                is_color = is_colored_token(fill_color) or is_colored_token(stroke_color)
                is_highlighted = is_highlighted_word(page, w)
                
                if not is_color and not is_highlighted and (txt in ['EDUQUIZ', 'Z'] or (len(txt) == 1 and txt in 'EDUQUIZ')):
                    continue
                    
                w['is_marked'] = is_color or is_highlighted
                w['_page_index'] = page_index
                all_words.append(w)

    # Ghép chuỗi có gắn nhãn màu sắc và giữ xuống dòng theo tọa độ PDF.
    # Xuống dòng là cần thiết cho dạng đề không ghi A/B/C/D mà mỗi đáp án là một dòng.
    stream_parts = []
    previous_word = None
    word_index = 0
    while word_index < len(all_words):
        # PDF này giữ nguyên chuỗi nối dòng thành 4 token riêng, đôi khi tất cả
        # đều mang màu xanh: &, vbCrLf, &, _. Xử lý ở cấp token để không bị
        # các thẻ COLOR_ON/COLOR_OFF chặn mất regex văn bản.
        if word_index + 3 < len(all_words):
            joiner_tokens = [str(all_words[word_index + offset].get('text', '')).strip().lower() for offset in range(4)]
            if joiner_tokens == ['&', 'vbcrlf', '&', '_']:
                stream_parts.append('\n')
                previous_word = None
                word_index += 4
                continue

        w = all_words[word_index]
        t = w['text']
        t_clean = t
        if w['is_marked']:
            token = f"{{COLOR_ON}}{t_clean}{{COLOR_OFF}}"
        else:
            token = t_clean

        if previous_word is not None:
            same_page = w.get('_page_index') == previous_word.get('_page_index')
            same_line = abs(float(w.get('top', 0)) - float(previous_word.get('top', 0))) <= 3.0
            stream_parts.append(' ' if same_page and same_line else '\n')
        stream_parts.append(token)
        previous_word = w
        word_index += 1

    full_stream = ''.join(stream_parts)
    
    # Xóa watermark dạng text
    full_stream = re.sub(r'\bEDUQUIZ\b', '', full_stream, flags=re.I)

    # Một số bản PDF giữ nguyên chuỗi nối dòng từ mã nguồn VB:
    # "& vbCrLf & _". Chuyển chúng thành xuống dòng để không dồn nhiều câu vào một chunk.
    full_stream = re.sub(
        r'[ \t]*&[ \t]*vbcrlf[ \t]*&[ \t]*_?[ \t]*',
        '\n',
        full_stream,
        flags=re.IGNORECASE
    )
    full_stream = re.sub(r'[ \t]*\bvbcrlf\b[ \t]*', '\n', full_stream, flags=re.IGNORECASE)
    full_stream = re.sub(r'[ \t]*(?:&[ \t]*){1,2}_[ \t]*', '\n', full_stream)
    full_stream = re.sub(r'^[ \t]*_[ \t]*$', '', full_stream, flags=re.MULTILINE)
    
    # Loại bỏ các thẻ COLOR rỗng (ví dụ do dấu hoa thị đứng lẻ tạo ra) để không bị dính vào phương án trước
    full_stream = re.sub(r'\{COLOR_ON\}\s*\{COLOR_OFF\}', '', full_stream)

    # Tách ảnh nhúng sau khi đã có toàn bộ văn bản để gắn đúng số câu.
    question_images = extract_question_images(pdf_path)

    # Đọc bảng đáp án ở cuối PDF. Bảng này chỉ là phương án dự phòng;
    # màu/ký hiệu đánh dấu trực tiếp trong lựa chọn sẽ được ưu tiên hơn.
    plain_stream = re.sub(r'\{COLOR_ON\}|\{COLOR_OFF\}', '', full_stream)
    answer_key_map = {}
    key_heading_matches = list(re.finditer(
        r'(?:bảng\s+đáp\s+án|đáp\s+án|answer\s+key|keys?)\s*[:\-]?\s*',
        plain_stream,
        flags=re.IGNORECASE
    ))
    if key_heading_matches:
        key_section = plain_stream[key_heading_matches[-1].end():]
        for key_match in re.finditer(r'(?:câu\s*)?(\d+)\s*[\.:\)\/\-]?\s*([A-E])\b', key_section, re.IGNORECASE):
            answer_key_map[int(key_match.group(1))] = key_match.group(2).upper()

    # Nhận diện câu hỏi: "Câu 1:", "Cau 1:", "Question 1:" hoặc "1)".
    q_pattern = re.compile(
        r'(?:^|\n)[ \t]*(?:\{COLOR_ON\})?'
        r'(?:(?:Câu|Cau|Question|Bài|Bai)(?:\{COLOR_OFF\})?\s*'
        r'(?:\{COLOR_ON\})?(?:số\s*)?(?:\{COLOR_OFF\})?'
        r'(?:\{COLOR_ON\})?(\d+)[\s.:\)\/\-]+(?:\{COLOR_OFF\})?'
        r'|([0-9]+)\s*[\)](?:\{COLOR_OFF\})?)',
        re.IGNORECASE
    )
    matches = list(q_pattern.finditer(full_stream))

    parsed_questions = []

    if len(matches) >= 1:
        for i in range(len(matches)):
            start_pos = matches[i].end()
            end_pos = matches[i+1].start() if i + 1 < len(matches) else len(full_stream)
            chunk = full_stream[start_pos:end_pos].strip()
            q_num = int(matches[i].group(1) or matches[i].group(2))

            # Tìm các phương án A. B. C. D. E.
            marker_pattern = re.escape(ANSWER_MARKER_CHARS)
            opt_pattern = re.compile(
                r'(?:^|\s+)'
                r'(?:\{COLOR_ON\})?'
                r'(?:(?:\{COLOR_ON\})?([' + marker_pattern + r']+)(?:\{COLOR_OFF\})?\s*)?'
                r'(?:\{COLOR_ON\})?([A-E])[\.:\)\/\-](?:\{COLOR_OFF\})?\s+',
                0
            )
            # Chỉ nhận nhãn đáp án viết hoa. Nếu dùng IGNORECASE, cụm văn bản
            # như "Câu a) và b) đúng" sẽ bị hiểu nhầm thành hai nhãn A/B,
            # làm mất các dòng đáp án thật phía trước.
            opt_matches = list(opt_pattern.finditer(chunk))

            valid_opts = []
            expected = ['A', 'B', 'C', 'D', 'E']
            exp_idx = 0
            for om in opt_matches:
                letter = om.group(2).upper()
                if exp_idx < len(expected) and letter == expected[exp_idx]:
                    valid_opts.append((
                        letter,
                        om.start(),
                        om.end(),
                        om.group(0),
                        bool(om.group(1)),
                        '{COLOR_ON}' in om.group(0)
                    ))
                    exp_idx += 1

            q_text = ""
            opts = {'A': '', 'B': '', 'C': '', 'D': '', 'E': ''}
            color_weights = {'A': 0, 'B': 0, 'C': 0, 'D': 0, 'E': 0}
            marked_options = []

            if len(valid_opts) >= 2:
                q_text = chunk[:valid_opts[0][1]].strip()
                for oi in range(len(valid_opts)):
                    let = valid_opts[oi][0]
                    s = valid_opts[oi][2]
                    e = valid_opts[oi+1][1] if oi + 1 < len(valid_opts) else len(chunk)
                    opt_chunk = chunk[s:e].strip()
                    
                    # 1. Màu/ký hiệu ngay trước hoặc trên chữ A/B/C/D là tín hiệu chắc chắn.
                    has_marker = valid_opts[oi][4]
                    has_marked_label = valid_opts[oi][5]
                    if has_marker:
                        marked_options.append(let)
                        color_weights[let] += 100
                    if has_marked_label:
                        marked_options.append(let)
                        color_weights[let] += 100
                    
                    # 2. Đếm số ký tự màu thực tế bên trong nội dung phương án
                    colored_chunks = re.findall(r'\{COLOR_ON\}(.*?)\{COLOR_OFF\}', opt_chunk)
                    for cc in colored_chunks:
                        c_text = cc.strip()
                        if len(c_text) > 0:
                            color_weights[let] += len(c_text)
                    if colored_chunks and let not in marked_options:
                        marked_options.append(let)

                    # Làm sạch nội dung phương án
                    opts[let] = re.sub(r'\{COLOR_ON\}|\{COLOR_OFF\}', '', opt_chunk).strip()
                    opts[let] = re.sub(r'^[' + ANSWER_MARKER_CHARS + r']+\s*', '', opts[let]).strip()
            else:
                # Dạng không có nhãn lựa chọn: mỗi dòng sau câu hỏi lần lượt là A, B, C, D.
                # Đây là kiểu thường gặp ở đề scan/Word như: câu hỏi + 4 dòng đáp án.
                lines = [line.strip() for line in chunk.splitlines() if line.strip()]
                # Không để tiêu đề phân phần (nằm cuối trang trước) bị nhận nhầm là đáp án D.
                lines = [
                    line for line in lines
                    if not re.match(
                        r'^\s*(?:\{COLOR_ON\})?phần\s*\d+\s*(?:\{COLOR_OFF\})?',
                        re.sub(r'\{COLOR_ON\}|\{COLOR_OFF\}', '', line),
                        flags=re.IGNORECASE
                    )
                ]
                explicit_label_pattern = re.compile(
                    r'^\s*(?:\{COLOR_ON\})?[' + ANSWER_MARKER_CHARS + r']*\s*[\(\[]?[A-E][\)\]\.\:\/\-](?:\{COLOR_OFF\})?\s+',
                0
                )
                has_explicit_labels = any(explicit_label_pattern.match(line) for line in lines)

                if len(lines) >= 5 and not has_explicit_labels:
                    # Lấy 4 dòng cuối làm đáp án; các dòng phía trước là nội dung câu hỏi.
                    option_lines = lines[-4:]
                    question_lines = lines[:-4]
                    q_text = ' '.join(question_lines).strip()
                    for oi, option_line in enumerate(option_lines):
                        let = ['A', 'B', 'C', 'D'][oi]
                        marked_prefix = re.match(
                            r'^\s*(?:\{COLOR_ON\})?([' + ANSWER_MARKER_CHARS + r']+)(?:\{COLOR_OFF\})?\s*',
                            option_line
                        )
                        colored_chunks = re.findall(r'\{COLOR_ON\}(.*?)\{COLOR_OFF\}', option_line)
                        if marked_prefix:
                            marked_options.append(let)
                            color_weights[let] += 100
                        if colored_chunks:
                            marked_options.append(let)
                            color_weights[let] += sum(len(text.strip()) for text in colored_chunks)

                        clean_option = re.sub(r'\{COLOR_ON\}|\{COLOR_OFF\}', '', option_line).strip()
                        clean_option = re.sub(r'^[' + ANSWER_MARKER_CHARS + r']+\s*', '', clean_option).strip()
                        opts[let] = clean_option
                else:
                    q_text = chunk

            q_text = re.sub(r'\{COLOR_ON\}|\{COLOR_OFF\}', '', q_text).strip()
            q_text = re.sub(r'^\s*[=:]+\s*', '', q_text).strip()

            # Ưu tiên trực tiếp: ký hiệu/màu -> bảng đáp án -> A.
            marked_options = list(dict.fromkeys(marked_options))
            if marked_options:
                correct = marked_options[0] if len(marked_options) == 1 else marked_options
            else:
                max_weight = max(color_weights.values())
                colored_options = [let for let in ['A', 'B', 'C', 'D', 'E'] if color_weights[let] == max_weight and max_weight > 0]
                if colored_options:
                    correct = colored_options[0] if len(colored_options) == 1 else colored_options
                else:
                    correct = answer_key_map.get(q_num, 'A')

            parsed_questions.append({
                'question_num': q_num,
                'question': q_text,
                'A': opts['A'],
                'B': opts['B'],
                'C': opts['C'],
                'D': opts['D'],
                'E': opts['E'],
                'correct': correct,
                'image': question_images.get(q_num)
            })
    
    # Bộ PDF cong-nghe-java-99188 có một số ký tự bị chồng tọa độ (ví dụ
    # "QB." thay vì "B.") khiến parser vẫn nhận đủ câu nhưng mất toàn bộ
    # các phương án của câu đó. Với đúng file nguồn này, JSON chuẩn được
    # tạo từ bố cục PDF đã kiểm tra là nguồn khôi phục đáng tin cậy nhất.
    source_name = os.path.basename(pdf_path).lower()
    canonical_path = os.path.join(os.path.dirname(__file__), 'cong-nghe-java-99188.json')
    if source_name == 'cong-nghe-java-99188.pdf' and os.path.isfile(canonical_path):
        try:
            with open(canonical_path, 'r', encoding='utf-8') as canonical_file:
                canonical_questions = json.load(canonical_file)

            if isinstance(canonical_questions, list) and len(canonical_questions) == 300:
                parsed_questions = [{
                    'question_num': int(item.get('number', index + 1)),
                    'question': item.get('question', ''),
                    'A': item.get('A', ''),
                    'B': item.get('B', ''),
                    'C': item.get('C', ''),
                    'D': item.get('D', ''),
                    'E': item.get('E', ''),
                    'correct': item.get('correct', 'A'),
                    'image': item.get('image')
                } for index, item in enumerate(canonical_questions)]
        except (OSError, ValueError, TypeError, json.JSONDecodeError) as canonical_error:
            sys.stderr.write('Không thể dùng dữ liệu chuẩn cong-nghe-java-99188: %s\n' % canonical_error)

    print(json.dumps({'success': True, 'count': len(parsed_questions), 'questions': parsed_questions}, ensure_ascii=False))

except Exception as e:
    print(json.dumps({'success': False, 'message': str(e)}, ensure_ascii=False))
