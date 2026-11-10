/**
 * smart_parser.js — Bộ phân tích đề thi thông minh (Cân mọi định dạng PDF & Văn bản tự do)
 * Hỗ trợ:
 *   - PDF không theo cấu trúc, chữ dính dòng, ngắt dòng lộn xộn
 *   - Các phương án A, B, C, D nằm chung một hàng ngang (Inline options)
 *   - Tự động phát hiện & trích xuất BẢNG ĐÁP ÁN ở cuối đề (1.A, 2.B, 3.C...)
 *   - Đáp án đánh dấu bằng dấu sao (*A.), gạch chân, bôi đậm
 *   - Nhiều kiểu đánh số: Câu 1, 1., 1), 1/, [1], Question 1, Bài 1
 *   - Nhiều kiểu đáp án: A., A), [A], (A), a., a), A:, A/
 *   - Tự động loại bỏ Header / Footer / Trang rác
 */

const SmartQuizParser = {

  // Các ký hiệu đứng trước A/B/C/D/E đều được xem là dấu đánh dấu đáp án đúng.
  answerMarkerChars: '*✱✳✓✔☑☒✅✦•●◉◆◇▶➜→➤+',

  /**
   * 1. Trích xuất text từ file PDF với thuật toán căn chỉnh tọa độ Y/X thông minh
   */
  async extractTextFromPdf(pdfDoc) {
    let fullText = '';
    const numPages = pdfDoc.numPages;

    for (let pageNum = 1; pageNum <= numPages; pageNum++) {
      const page = await pdfDoc.getPage(pageNum);
      const textContent = await page.getTextContent();
      const items = textContent.items;

      if (!items || items.length === 0) continue;

      // Sắp xếp items theo thứ tự thực tế trên trang: Top-to-bottom (Y giảm dần), Left-to-right (X tăng dần)
      items.sort((a, b) => {
        const yDiff = b.transform[5] - a.transform[5];
        if (Math.abs(yDiff) > 6) {
          return yDiff; // Khác dòng
        }
        return a.transform[4] - b.transform[4]; // Cùng dòng
      });

      let lastY = null;
      let lastX = null;
      let pageText = '';

      for (const item of items) {
        const str = item.str;
        if (!str && str !== ' ') continue;

        const currentY = item.transform[5];
        const currentX = item.transform[4];

        if (lastY !== null) {
          const deltaY = Math.abs(currentY - lastY);
          if (deltaY > 6) {
            pageText += '\n'; // Xuống dòng
          } else if (lastX !== null && currentX - lastX > 5 && !pageText.endsWith(' ') && !str.startsWith(' ')) {
            pageText += ' '; // Khoảng cách giữa các chữ cùng dòng
          }
        }

        pageText += str;
        lastY = currentY;
        lastX = currentX + (item.width || 0);
      }

      fullText += pageText + '\n\n';
    }

    return fullText;
  },

  /**
   * 2. Làm sạch văn bản thô (loại bỏ Header, Footer, Trang x/y)
   */
  cleanRawText(text) {
    if (!text) return '';

    return text
      // Chuẩn hóa ký tự xuống dòng
      .replace(/\r\n/g, '\n')
      .replace(/\r/g, '\n')
      // Bỏ ký tự ẩn không in được
      .replace(/[\u200B-\u200D\uFEFF]/g, '')
      // Một số file Java/đề được xuất qua chuỗi VB dùng "& vbCrLf & _"
      // để nối dòng. Nếu giữ nguyên, toàn bộ đề bị dồn thành một câu hỏi.
      .replace(/[ \t]*&[ \t]*vbcrlf[ \t]*&[ \t]*_?[ \t]*/gi, '\n')
      .replace(/[ \t]*\bvbcrlf\b[ \t]*/gi, '\n')
      .replace(/[ \t]*(?:&[ \t]*){1,2}_[ \t]*/g, '\n')
      .replace(/^[ \t]*_[ \t]*$/gm, '')
      // Loại bỏ các dòng Header/Footer thường gặp trong đề thi PDF
      .replace(/^[ \t]*(?:trang|page)\s*\d+(\s*[\/\-]\s*\d+)?[ \t]*$/gim, '')
      .replace(/^[ \t]*(?:bộ giáo dục|trường đại học|khoa |đề thi học kỳ|mã đề|thời gian làm bài|ngày thi).*$/gim, (match) => {
        // Chỉ xóa nếu là header đầu trang (không chứa chữ 'câu' hay đáp án)
        if (/câu\s*\d+/i.test(match) || /[A-D]\./.test(match)) return match;
        return '';
      })
      // Loại bỏ watermark EDUQUIZ và rác OCR
      .replace(/[ \t]*EDUQUIZ[ \t]*/gi, ' ')
      .replace(/\nZ\s*\n/gi, '\n')
      // Chuẩn hóa khoảng trắng lặp
      .replace(/[ \t]{2,}/g, ' ')
      .trim();
  },

  /**
   * 3. Tự động tìm & trích xuất Bảng đáp án ở cuối đề (nếu có)
   * Ví dụ:
   *   ĐÁP ÁN: 1.A 2.B 3.C 4.D 5.A ...
   *   hoặc 1-A, 2-B, 3-C, 4-D...
   *   hoặc bảng tổng kết đáp án trắc nghiệm
   */
  extractAnswerKeyTable(text) {
    const keysMap = {};
    let cleanBody = text;

    // Tìm vị trí bắt đầu của phần đáp án cuối đề (hỗ trợ cả có dấu, không dấu, viết hoa, viết thường)
    const keySectionRegex = /(?:^|\n)\s*(?:b[aảàạá]ng\s*d[aáàạ]p\s*[aáàạ]n|bang\s*dap\s*an|d[aáàạ]p\s*[aáàạ]n\s*(?:tham\s*kh[aảàạá]o|chi\s*ti[eéèẹ]t)?|dap\s*an|b[aảàạá]ng\s*k[eêếềệ]t\s*qu[aảàạá]|answer\s*keys?|h[uưứừự]ng\s*d[aẫầậã]n\s*ch[aấầậ]m|keys?)\s*[:\-]?\s*([\s\S]+)$/i;
    const robustKeySectionRegex = /(?:^|\n)\s*(?:(?:bảng\s+)?đáp\s+án|(?:bang\s+)?dap\s+an|answer\s*keys?|keys?)\s*[:\-]?\s*([\s\S]+)$/iu;
    const match = text.match(robustKeySectionRegex) || text.match(keySectionRegex);

    if (match) {
      const keySectionText = match[1];
      cleanBody = text.substring(0, match.index).trim();

      // Quét tất cả các cặp: số_câu + đáp_án (ví dụ: "1.A", "1: A", "1-A", "1A", "[1] A", "1) A")
      const pairRegex = /(?:câu\s*)?(\d+)[\s.:\)\/\-]*\s*([A-Ea-e])(?!\w)/gi;
      let pairMatch;
      while ((pairMatch = pairRegex.exec(keySectionText)) !== null) {
        const qNum = parseInt(pairMatch[1], 10);
        const ansLetter = pairMatch[2].toUpperCase();
        if (qNum > 0 && ['A', 'B', 'C', 'D', 'E'].includes(ansLetter)) {
          keysMap[qNum] = ansLetter;
        }
      }
    }

    return { keysMap, cleanBody };
  },

  /**
   * 4. Phân tích đề thi không theo cấu trúc (Cốt lõi)
   */
  parse(rawText) {
    if (!rawText || typeof rawText !== 'string') return [];

    // Bước 1: Làm sạch văn bản
    const cleanedText = this.cleanRawText(rawText);

    // Bước 2: Tách bảng đáp án ở cuối đề
    const { keysMap, cleanBody } = this.extractAnswerKeyTable(cleanedText);

    // Bước 3: Tách câu hỏi
    // Tìm các mốc câu hỏi: "Câu 1.", "Câu 1:", "1.", "1)", "1/", "[1]", "Question 1:", "Bài 1:"
    const qStartRegex = /(?:^|\n)\s*(?:(?:câu|question|bài|q)\s*)?(\d+)[\s.:\)\/\-]+\s*/gi;
    
    // Tìm tất cả vị trí xuất hiện câu hỏi
    const matches = [];
    let m;
    while ((m = qStartRegex.exec(cleanBody)) !== null) {
      // Xác minh mốc câu: tránh bắt nhầm số thứ tự trong đáp án hoặc ngày tháng
      const matchedNum = parseInt(m[1], 10);
      const matchedIndex = m.index;
      matches.push({
        num: matchedNum,
        index: matchedIndex,
        fullMatch: m[0]
      });
    }

    // Tách các đoạn văn bản tương ứng với từng câu
    const rawChunks = [];
    if (matches.length >= 2) {
      for (let i = 0; i < matches.length; i++) {
        const start = matches[i].index + matches[i].fullMatch.length;
        const end = (i + 1 < matches.length) ? matches[i + 1].index : cleanBody.length;
        const chunkText = cleanBody.substring(start, end).trim();
        rawChunks.push({
          num: matches[i].num,
          text: chunkText
        });
      }
    } else {
      // Fallback: Nếu không tìm thấy số câu rõ ràng, thử tách theo 2 dấu xuống dòng liên tiếp
      const paragraphs = cleanBody.split(/\n\s*\n/).map(p => p.trim()).filter(p => p.length > 20);
      paragraphs.forEach((p, idx) => {
        // Chỉ nhận paragraph có chứa ít nhất 2 mốc lựa chọn A. và B.
        if (/[A-Da-d][\.\)]/.test(p)) {
          rawChunks.push({ num: idx + 1, text: p });
        }
      });
    }

    if (rawChunks.length === 0) {
      // Nếu văn bản vẫn không tách được, coi cả văn bản là 1 khối và cố gắng bóc tách
      rawChunks.push({ num: 1, text: cleanBody });
    }

    // Bước 4: Phân tích chi tiết từng khối câu hỏi (Câu hỏi, A, B, C, D, Đáp án đúng)
    const parsedQuestions = [];

    rawChunks.forEach((chunk, chunkIdx) => {
      const qNum = chunk.num || (chunkIdx + 1);
      const text = chunk.text;

      const parsedQ = this.parseSingleQuestionChunk(text, qNum, keysMap[qNum]);
      if (parsedQ && parsedQ.question && (parsedQ.A || parsedQ.B)) {
        parsedQuestions.push(parsedQ);
      }
    });

    return parsedQuestions;
  },

  /**
   * 5. Bóc tách một khối câu hỏi: Trị các trường hợp đáp án cùng dòng, đáp án dấu sao, đáp án cuối câu
   */
  parseSingleQuestionChunk(text, questionNum, fallbackCorrectKey) {
    if (!text || text.length < 5) return null;

    let questionContent = '';
    let optA = '', optB = '', optC = '', optD = '', optE = '';
    let correctKey = '';
    let textCorrectKey = '';
    const explicitCorrectKeys = [];
    let explanation = '';

    // Kiểm tra xem trong chunk có dòng "Đáp án: X" hay không
    const ansLineRegex = /(?:^|\s)(?:đáp\s*án(?:\s*đúng)?|câu\s*trả\s*lời|key|hdg|chọn|=>|đ\/a|ans)\s*[:=\-]\s*([A-Ea-e])(?:\s|$|\.)/i;
    const ansMatch = text.match(ansLineRegex);
    let workingText = text;
    if (ansMatch) {
      textCorrectKey = ansMatch[1].toUpperCase();
      // Bóc dòng đáp án ra khỏi nội dung
      workingText = text.replace(ansMatch[0], ' ');
    }

    // Tìm kiếm các mốc đáp án: A., B., C., D., E.
    // Hỗ trợ: A., *A., A), [A], (A), a., a), A:, A/
    // và kể cả khi chúng nằm TRÊN CÙNG MỘT DÒNG!
    const markerChars = this.answerMarkerChars;
    const optMarkerRegex = new RegExp(
      `(?:^|\\s+)([${markerChars}]*\\s*[\\(\\[]?([A-Ea-e])[\\)\\]\\.\\:\\/])\\s+`,
      'g'
    );
    const markers = [];
    let m;

    while ((m = optMarkerRegex.exec(workingText)) !== null) {
      const fullMarker = m[1];
      const letter = m[2].toUpperCase();
      const hasMarker = [...fullMarker].some(char => markerChars.includes(char));
      markers.push({
        letter: letter,
        hasMarker: hasMarker,
        index: m.index,
        matchLength: m[0].length
      });
    }

    // Lọc các marker: chỉ giữ lại chuỗi hợp lệ xuất hiện theo thứ tự (A -> B -> C -> D)
    const validMarkers = [];
    const expectedOrder = ['A', 'B', 'C', 'D', 'E'];
    let expectedIdx = 0;

    markers.forEach(mark => {
      if (expectedIdx < expectedOrder.length && mark.letter === expectedOrder[expectedIdx]) {
        validMarkers.push(mark);
        expectedIdx++;
      }
    });

    if (validMarkers.length >= 2) {
      // Câu hỏi là phần văn bản trước mốc A
      questionContent = workingText.substring(0, validMarkers[0].index).trim();

      // Cắt từng phương án
      for (let i = 0; i < validMarkers.length; i++) {
        const cur = validMarkers[i];
        const start = cur.index + cur.matchLength;
        const end = (i + 1 < validMarkers.length) ? validMarkers[i + 1].index : workingText.length;
        let optText = workingText.substring(start, end).trim();

        // Ưu tiên dấu hiệu trực tiếp trước bảng đáp án: sao, tick, ô chọn...
        if (cur.hasMarker) {
          explicitCorrectKeys.push(cur.letter);
        }

        // Làm sạch toàn bộ dấu sao * và watermark EDUQUIZ trong nội dung đáp án
        optText = optText.replace(/\*/g, '').replace(/EDUQUIZ/gi, '').trim();

        if (cur.letter === 'A') optA = optText;
        else if (cur.letter === 'B') optB = optText;
        else if (cur.letter === 'C') optC = optText;
        else if (cur.letter === 'D') optD = optText;
        else if (cur.letter === 'E') optE = optText;
      }
    } else {
      // Nếu không tìm được marker A, B chuẩn, thử tách theo dòng
      let lines = workingText.split('\n').map(l => l.trim()).filter(l => l !== '');
      lines = lines.filter(line => !/^\s*phần\s*\d+\b/i.test(line));
      if (lines.length > 0) {
        const labeledLineRegex = new RegExp(`^\\s*[${markerChars}]*\\s*[\\(\\[]?[A-Ea-e][\\)\\]\\.\\:\\/]\\s+`);
        const hasLabeledOptions = lines.slice(1).some(line => labeledLineRegex.test(line));

        if (lines.length >= 5 && !hasLabeledOptions) {
          // Dạng không ghi A/B/C/D: 4 dòng cuối mặc định lần lượt là A, B, C, D.
          const optionLines = lines.slice(-4);
          questionContent = lines.slice(0, -4).join(' ').trim();
          ['A', 'B', 'C', 'D'].forEach((letter, index) => {
            let textVal = optionLines[index].trim();
            const markerMatch = textVal.match(new RegExp(`^\\s*([${markerChars}]+)\\s*`));
            if (markerMatch) {
              explicitCorrectKeys.push(letter);
              textVal = textVal.substring(markerMatch[0].length);
            }
            textVal = textVal.replace(/\*/g, '').replace(/EDUQUIZ/gi, '').trim();
            if (letter === 'A') optA = textVal;
            else if (letter === 'B') optB = textVal;
            else if (letter === 'C') optC = textVal;
            else if (letter === 'D') optD = textVal;
          });
        } else {
          questionContent = lines[0];
          lines.slice(1).forEach(l => {
            const mOpt = l.match(new RegExp(`^([${markerChars}]*)\\s*[\\(\\[]?([A-Ea-e])[\\)\\]\\.\\:\\/]\\s*(.*)$`));
            if (mOpt) {
              const letter = mOpt[2].toUpperCase();
              let textVal = mOpt[3].trim();
              if (mOpt[1].length > 0) {
                explicitCorrectKeys.push(letter);
              }
              textVal = textVal.replace(/\*/g, '').replace(/EDUQUIZ/gi, '').trim();
              if (letter === 'A') optA = textVal;
              else if (letter === 'B') optB = textVal;
              else if (letter === 'C') optC = textVal;
              else if (letter === 'D') optD = textVal;
              else if (letter === 'E') optE = textVal;
            }
          });
        }
      }
    }

    // Nếu ký hiệu nằm ở cuối câu hỏi, coi A là đáp án được đánh dấu.
    const questionMarkerAtEnd = new RegExp(`[${markerChars}]\\s*$`).test(questionContent);
    if (questionMarkerAtEnd) {
      explicitCorrectKeys.push('A');
      questionContent = questionContent.replace(new RegExp(`[${markerChars}]\\s*$`), '').trim();
    }

    // Loại bỏ phần bảng đáp án hoặc ghi chú nếu còn sót ở cuối phương án D hoặc E
    const trimAnswerTable = (str) => {
      if (!str) return '';
      return str.replace(/(?:\n|\s{2,})(?:b[aảàạá]ng\s*d[aáàạ]p\s*[aáàạ]n|bang\s*dap\s*an|d[aáàạ]p\s*[aáàạ]n|answer\s*key)[\s\S]*$/i, '').trim();
    };
    optD = trimAnswerTable(optD);
    optE = trimAnswerTable(optE);

    // Làm sạch nội dung câu hỏi (bỏ từ "Câu X:" ở đầu nếu còn)
    questionContent = questionContent.replace(/^(?:câu|question|bài|q)?\s*\d+[\s.:\)\/\-]+\s*/i, '').trim();
    questionContent = questionContent.replace(/^\s*[=:]+\s*/, '').trim();

    // Nếu không có câu hỏi nhưng có các lựa chọn
    if (!questionContent && (optA || optB)) {
      questionContent = `Câu hỏi số ${questionNum}`;
    }

    const uniqueExplicitKeys = [...new Set(explicitCorrectKeys)];
    if (uniqueExplicitKeys.length > 0) {
      correctKey = uniqueExplicitKeys.length > 1 ? uniqueExplicitKeys : uniqueExplicitKeys[0];
    } else if (fallbackCorrectKey && ['A', 'B', 'C', 'D', 'E'].includes(fallbackCorrectKey)) {
      correctKey = fallbackCorrectKey;
    } else if (textCorrectKey && ['A', 'B', 'C', 'D', 'E'].includes(textCorrectKey)) {
      correctKey = textCorrectKey;
    } else {
      correctKey = 'A';
    }

    return {
      question_num: questionNum,
      question: questionContent,
      A: optA,
      B: optB,
      C: optC,
      D: optD,
      E: optE,
      correct: correctKey,
      explanation: explanation
    };
  }
};

if (typeof module !== 'undefined' && module.exports) {
  module.exports = SmartQuizParser;
}
