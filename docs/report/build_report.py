"""
Builds the FYP report (docs/report/Rento_FYP_Report.docx) from the content files in docs/report/content/.

    ml/.venv/bin/pip install python-docx
    ml/.venv/bin/python docs/report/build_report.py

Formatting follows the Lincoln University College FYP guidelines:
  A4; margins 1" top/right/bottom, 1.5" left; Times New Roman 12 pt, 1.5 line spacing (executive summary and
  references single-spaced); chapter headings 14 pt bold centred ALL CAPS on a new page; level 2 12 pt bold;
  level 3 12 pt bold italic; figures centred with a 10 pt italic caption below; tables centred with a 10 pt
  bold caption above; Roman page numbers for the preliminary pages, Arabic from Chapter 1, bottom centre.
The table of contents and the lists of figures and tables are Word fields: Word updates them when the file
is opened (or right-click > Update field).

Content markup (docs/report/content/*.md, processed in file-name order):
  # CHAPTER 1: INTRODUCTION        chapter heading (new page)       ## 1.1 Title   level 2   ### 1.1.1 Title  level 3
  plain lines                      paragraph (**bold**, *italic*)   - item / 1. item   bullet / numbered list
  ![Figure 4.1 Caption](path)      figure (path relative to docs/report/)
  Table: Table 3.1 Caption         then | a | b | rows (first row = header)
  > text                           indented note paragraph           [[PAGEBREAK]]
  %% comment                       ignored                            {{REFERENCES}} references list
  $$ p = b(1 + x) $$ (2.1)         numbered equation (matplotlib mathtext, rendered to an image)
  ```Figure 5.1 Caption            code excerpt up to the closing ``` (captioned as a figure)
"""
from __future__ import annotations

import re
from pathlib import Path

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK, WD_LINE_SPACING
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Inches, Pt, RGBColor

HERE = Path(__file__).resolve().parent
CONTENT = HERE / "content"
OUT = HERE / "Rento_FYP_Report.docx"
FONT = "Times New Roman"

# ------------------------------------------------------------------ document set-up


def set_font(run, size=12, bold=None, italic=None):
    run.font.name = FONT
    run.font.size = Pt(size)
    rpr = run._element.get_or_add_rPr()
    rfonts = rpr.find(qn("w:rFonts"))
    if rfonts is None:
        rfonts = OxmlElement("w:rFonts")
        rpr.append(rfonts)
    for a in ("w:ascii", "w:hAnsi", "w:cs", "w:eastAsia"):
        rfonts.set(qn(a), FONT)
    if bold is not None:
        run.bold = bold
    if italic is not None:
        run.italic = italic


def style_font(style, size, bold=False, italic=False, align=None, before=0, after=6, spacing=1.5, keep_next=False):
    style.font.name = FONT
    style.font.size = Pt(size)
    style.font.bold = bold
    style.font.italic = italic
    style.font.color.rgb = RGBColor(0, 0, 0)
    rpr = style.element.get_or_add_rPr()
    rfonts = rpr.find(qn("w:rFonts"))
    if rfonts is None:
        rfonts = OxmlElement("w:rFonts")
        rpr.append(rfonts)
    for a in ("w:ascii", "w:hAnsi", "w:cs", "w:eastAsia"):
        rfonts.set(qn(a), FONT)
    for theme in ("w:asciiTheme", "w:hAnsiTheme", "w:cstheme", "w:eastAsiaTheme"):
        if rfonts.get(qn(theme)) is not None:
            del rfonts.attrib[qn(theme)]
    pf = style.paragraph_format
    pf.space_before = Pt(before)
    pf.space_after = Pt(after)
    pf.line_spacing = spacing
    pf.keep_with_next = keep_next
    if align is not None:
        pf.alignment = align


def build_styles(doc):
    st = doc.styles
    style_font(st["Normal"], 12, spacing=1.5, after=6)
    st["Normal"].paragraph_format.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    style_font(st["Heading 1"], 14, bold=True, align=WD_ALIGN_PARAGRAPH.CENTER, before=0, after=18, keep_next=True)
    style_font(st["Heading 2"], 12, bold=True, align=WD_ALIGN_PARAGRAPH.LEFT, before=12, after=6, keep_next=True)
    style_font(st["Heading 3"], 12, bold=True, italic=True, align=WD_ALIGN_PARAGRAPH.LEFT, before=10, after=4, keep_next=True)
    for name in ("Heading 1", "Heading 2", "Heading 3"):
        st[name].font.all_caps = name == "Heading 1"
    # Every chapter and preliminary heading starts on a new page (no separate break paragraphs, which
    # would leave a blank page when the previous page is exactly full).
    st["Heading 1"].paragraph_format.page_break_before = True
    for name, kw in [("Figure Caption", dict(size=10, italic=True, align=WD_ALIGN_PARAGRAPH.CENTER, before=4, after=12, spacing=1.0)),
                     ("Table Caption", dict(size=10, bold=True, align=WD_ALIGN_PARAGRAPH.CENTER, before=12, after=4, spacing=1.0, keep_next=True)),
                     ("Single", dict(size=12, spacing=1.0, after=6)),
                     ("Reference", dict(size=12, spacing=1.0, after=8)),
                     ("Table Text", dict(size=10, spacing=1.0, after=0)),
                     ("Note", dict(size=11, italic=True, spacing=1.15, after=6)),
                     ("Centered", dict(size=12, align=WD_ALIGN_PARAGRAPH.CENTER, spacing=1.5, after=0))]:
        s = st.add_style(name, 1) if name not in [x.name for x in st] else st[name]
        s.base_style = st["Normal"]
        style_font(s, **kw)
    st["Reference"].paragraph_format.left_indent = Inches(0.5)
    st["Reference"].paragraph_format.first_line_indent = Inches(-0.5)
    st["Reference"].paragraph_format.alignment = WD_ALIGN_PARAGRAPH.LEFT
    st["Table Text"].paragraph_format.alignment = WD_ALIGN_PARAGRAPH.LEFT
    st["Note"].paragraph_format.left_indent = Inches(0.4)
    for lst in ("List Bullet", "List Number"):
        style_font(st[lst], 12, spacing=1.5, after=2)
        st[lst].paragraph_format.alignment = WD_ALIGN_PARAGRAPH.LEFT


def margins(section):
    section.page_height, section.page_width = Cm(29.7), Cm(21.0)
    section.top_margin = section.bottom_margin = section.right_margin = Inches(1)
    section.left_margin = Inches(1.5)
    section.footer_distance = Inches(0.5)


def field(paragraph, instr, placeholder=""):
    """Adds a Word field (PAGE, TOC ...) to a paragraph."""
    r = paragraph.add_run()
    b = OxmlElement("w:fldChar"); b.set(qn("w:fldCharType"), "begin"); b.set(qn("w:dirty"), "true")
    i = OxmlElement("w:instrText"); i.set(qn("xml:space"), "preserve"); i.text = f" {instr} "
    s = OxmlElement("w:fldChar"); s.set(qn("w:fldCharType"), "separate")
    t = OxmlElement("w:t"); t.text = placeholder
    e = OxmlElement("w:fldChar"); e.set(qn("w:fldCharType"), "end")
    r._r.append(b); r._r.append(i); r._r.append(s); r._r.append(t); r._r.append(e)
    set_font(r)
    return r


def page_numbers(section, fmt):
    """fmt: None = no number, 'lowerRoman' or 'decimal', restarting at 1."""
    section.footer.is_linked_to_previous = False
    footer = section.footer
    for p in footer.paragraphs:
        p.clear()
    sect = section._sectPr
    pg = sect.find(qn("w:pgNumType"))
    if pg is None:
        pg = OxmlElement("w:pgNumType"); sect.append(pg)
    if fmt:
        pg.set(qn("w:fmt"), fmt); pg.set(qn("w:start"), "1")
        p = footer.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        field(p, "PAGE", "1")


def update_fields_on_open(doc):
    settings = doc.settings.element
    uf = OxmlElement("w:updateFields"); uf.set(qn("w:val"), "true")
    settings.append(uf)


# ------------------------------------------------------------------ inline text

INLINE = re.compile(r"(\*\*[^*]+\*\*|\*[^*]+\*|`[^`]+`)")


def add_text(p, text, size=None, bold=None, italic=None):
    for part in INLINE.split(text):
        if not part:
            continue
        b, i, mono = bold, italic, False
        if part.startswith("**") and part.endswith("**"):
            part, b = part[2:-2], True
        elif part.startswith("*") and part.endswith("*"):
            part, i = part[1:-1], True
        elif part.startswith("`") and part.endswith("`"):
            part, mono = part[1:-1], True
        r = p.add_run(part)
        set_font(r, size or (p.style.font.size.pt if p.style.font.size else 12), bold=b, italic=i)
        if mono:
            r.font.name = "Courier New"
            r._element.rPr.rFonts.set(qn("w:ascii"), "Courier New"); r._element.rPr.rFonts.set(qn("w:hAnsi"), "Courier New")
            r.font.size = Pt((size or 12) - 1)
    return p


def para(doc, text="", style="Normal", align=None, size=None, bold=None, italic=None, before=None, after=None):
    p = doc.add_paragraph(style=style)
    if align is not None:
        p.alignment = align
    if before is not None:
        p.paragraph_format.space_before = Pt(before)
    if after is not None:
        p.paragraph_format.space_after = Pt(after)
    if text:
        add_text(p, text, size=size, bold=bold, italic=italic)
    return p


def page_break(doc):
    doc.add_paragraph().add_run().add_break(WD_BREAK.PAGE)


def set_cell_background(cell, hex_colour):
    tcpr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd"); shd.set(qn("w:val"), "clear"); shd.set(qn("w:color"), "auto"); shd.set(qn("w:fill"), hex_colour)
    tcpr.append(shd)


def repeat_header(row):
    trpr = row._tr.get_or_add_trPr()
    h = OxmlElement("w:tblHeader"); h.set(qn("w:val"), "true")
    trpr.append(h)


def add_table(doc, caption, rows):
    # Optional column widths in inches at the end of the caption: "Table 2.2 Caption {widths=1.0,0.68,...}"
    widths = None
    m = re.search(r"\s*\{widths=([\d.,\s]+)\}\s*$", caption)
    if m:
        widths = [float(x) for x in m.group(1).split(",")]
        caption = caption[:m.start()]
    para(doc, caption, style="Table Caption")
    table = doc.add_table(rows=len(rows), cols=len(rows[0]))
    if widths:
        table.autofit = False
        layout = OxmlElement("w:tblLayout"); layout.set(qn("w:type"), "fixed")
        table._tbl.tblPr.append(layout)
        for col, w in zip(table._tbl.tblGrid.findall(qn("w:gridCol")), widths):
            col.set(qn("w:w"), str(int(w * 1440)))
        for r in table.rows:
            for c, w in zip(r.cells, widths):
                c.width = Inches(w)
    table.style = "Table Grid"
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    for r, row in enumerate(rows):
        for c, text in enumerate(row):
            cell = table.cell(r, c)
            cell.text = ""
            p = cell.paragraphs[0]
            p.style = doc.styles["Table Text"]
            add_text(p, text.strip(), size=10, bold=True if r == 0 else None)
            if r == 0:
                set_cell_background(cell, "F2F2F2")
    repeat_header(table.rows[0])
    # Rows never break across pages; short tables stay on one page with their caption.
    for i, row in enumerate(table.rows):
        trpr = row._tr.get_or_add_trPr()
        trpr.append(OxmlElement("w:cantSplit"))
        if len(rows) <= 12 and i < len(rows) - 1:
            for cell in row.cells:
                for cp in cell.paragraphs:
                    cp.paragraph_format.keep_with_next = True
    # A little padding inside the cells.
    tblpr = table._tbl.tblPr
    mar = OxmlElement("w:tblCellMar")
    for side, w in (("top", 40), ("bottom", 40), ("left", 60), ("right", 60)):
        el = OxmlElement(f"w:{side}"); el.set(qn("w:w"), str(w)); el.set(qn("w:type"), "dxa"); mar.append(el)
    tblpr.append(mar)
    para(doc, "", after=4)


def add_figure(doc, caption, path, width_in=6.0):
    p = doc.add_paragraph(style="Normal")
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.keep_with_next = True
    p.paragraph_format.line_spacing = 1.0
    full = HERE / path
    if full.exists():
        from PIL import Image  # noqa: WPS433 (matplotlib dependency)
        with Image.open(full) as im:
            w, h = im.size
        # fit inside the requested width and 9.0" height (text area is 5.77" x 9.69", less the caption)
        width = min(width_in, 9.0 * w / h)
        p.add_run().add_picture(str(full), width=Inches(width))
    else:
        add_text(p, f"[INSERT FIGURE: {path}]", italic=True)
    para(doc, caption, style="Figure Caption")


def add_code(doc, code_lines, caption):
    """Code excerpt in a shaded single-cell table (Courier New 8.5 pt), captioned as a figure below."""
    table = doc.add_table(rows=1, cols=1)
    table.style = "Table Grid"
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    cell = table.cell(0, 0)
    set_cell_background(cell, "F6F6F6")
    cell.text = ""
    for n, text in enumerate(code_lines):
        p = cell.paragraphs[0] if n == 0 else cell.add_paragraph()
        p.paragraph_format.line_spacing = 1.0
        p.paragraph_format.space_after = Pt(0)
        p.paragraph_format.space_before = Pt(0)
        p.paragraph_format.keep_with_next = True
        p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        r = p.add_run(text if text else " ")
        r.font.name = "Courier New"
        r._element.rPr.rFonts.set(qn("w:eastAsia"), "Courier New")
        r.font.size = Pt(8.5)
    tblpr = table._tbl.tblPr
    mar = OxmlElement("w:tblCellMar")
    for side, w in (("top", 80), ("bottom", 80), ("left", 120), ("right", 120)):
        el = OxmlElement(f"w:{side}"); el.set(qn("w:w"), str(w)); el.set(qn("w:type"), "dxa"); mar.append(el)
    tblpr.append(mar)
    row = table.rows[0]._tr.get_or_add_trPr()
    row.append(OxmlElement("w:cantSplit"))
    if caption:
        para(doc, caption, style="Figure Caption")


# ------------------------------------------------------------------ equations

EQ_DIR = HERE / "figures" / "equations"


def render_equation(latex: str, name: str) -> Path:
    """Renders a LaTeX-style formula (matplotlib mathtext) to a 300-dpi PNG."""
    import matplotlib
    matplotlib.use("Agg")
    import matplotlib.pyplot as plt
    EQ_DIR.mkdir(parents=True, exist_ok=True)
    out = EQ_DIR / f"{name}.png"
    plt.rcParams.update({"mathtext.fontset": "stix", "font.family": "STIXGeneral"})
    fig = plt.figure(figsize=(0.01, 0.01))
    fig.text(0, 0, f"${latex}$", fontsize=13)
    fig.savefig(out, dpi=300, bbox_inches="tight", pad_inches=0.04, transparent=False, facecolor="white")
    plt.close(fig)
    return out


def add_equation(doc, latex, number):
    """Centred formula with its number right-aligned, e.g. (2.1)."""
    img = render_equation(latex, "eq_" + number.strip("()").replace(".", "_"))
    from PIL import Image
    with Image.open(img) as im:
        w_px, h_px = im.size
    p = doc.add_paragraph(style="Normal")
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    p.paragraph_format.line_spacing = 1.0
    p.paragraph_format.space_before = Pt(6)
    p.paragraph_format.space_after = Pt(6)
    stops = p.paragraph_format.tab_stops
    from docx.enum.text import WD_TAB_ALIGNMENT
    # Text width is 8.27" - 1.5" - 1.0" = 5.77": centre the formula, number flush right.
    stops.add_tab_stop(Inches(2.885), WD_TAB_ALIGNMENT.CENTER)
    stops.add_tab_stop(Inches(5.77), WD_TAB_ALIGNMENT.RIGHT)
    p.add_run("\t")
    width_in = min(4.9, w_px / 300)
    p.add_run().add_picture(str(img), width=Inches(width_in))
    r = p.add_run("\t" + number)
    set_font(r, 12)
    # Raise the number to the vertical middle of the formula (w:position is in half-points).
    height_pt = width_in * h_px / w_px * 72
    pos = OxmlElement("w:position"); pos.set(qn("w:val"), str(max(0, int(height_pt - 8))))
    r._r.get_or_add_rPr().append(pos)


# ------------------------------------------------------------------ content files

def render_markdown(doc, text, refs):
    lines = text.splitlines()
    i = 0
    first_chapter = [True]
    while i < len(lines):
        line = lines[i].rstrip()
        if not line or line.startswith("%%"):
            i += 1
            continue
        if line == "[[PAGEBREAK]]":
            page_break(doc)
        elif line == "{{REFERENCES}}":
            for ref in refs:
                para(doc, ref, style="Reference")
        elif line.startswith("# "):
            title = line[2:].strip()
            if title.upper().startswith("CHAPTER 1:") and first_chapter[0]:
                # Arabic page numbers from Chapter 1.
                sec = doc.add_section(WD_SECTION.NEW_PAGE)
                margins(sec)
                page_numbers(sec, "decimal")
                first_chapter[0] = False
            doc.add_paragraph(title.upper(), style="Heading 1")
        elif line.startswith("### "):
            doc.add_paragraph(line[4:].strip(), style="Heading 3")
        elif line.startswith("## "):
            doc.add_paragraph(line[3:].strip(), style="Heading 2")
        elif line.startswith("```"):
            caption = line[3:].strip()
            code = []
            i += 1
            while i < len(lines) and not lines[i].startswith("```"):
                code.append(lines[i].rstrip())
                i += 1
            add_code(doc, code, caption)
        elif line.startswith("$$"):
            m = re.match(r"\$\$(.+?)\$\$\s*(\(\d+\.\d+\))", line)
            add_equation(doc, m.group(1).strip(), m.group(2))
        elif line.startswith("!["):
            m = re.match(r"!\[(.+?)\]\((.+?)\)(?:\{width=([\d.]+)\})?", line)
            add_figure(doc, m.group(1), m.group(2), float(m.group(3)) if m.group(3) else 6.0)
        elif line.startswith("Table: "):
            caption = line[7:].strip()
            rows = []
            i += 1
            while i < len(lines) and lines[i].strip().startswith("|"):
                cells = [c for c in lines[i].strip().strip("|").split("|")]
                if not all(re.fullmatch(r"\s*:?-{2,}:?\s*", c) for c in cells):
                    rows.append(cells)
                i += 1
            add_table(doc, caption, rows)
            continue
        elif re.match(r"^- ", line):
            add_text(doc.add_paragraph(style="List Bullet"), line[2:].strip())
        elif re.match(r"^\d+\. ", line):
            # Numbered lists restart at 1 each time: the number is written out with a hanging indent
            # (Word's List Number style would continue one numbering across the whole document).
            num = re.match(r"^(\d+)\.", line).group(1)
            p = doc.add_paragraph(style="Normal")
            p.paragraph_format.left_indent = Inches(0.35)
            p.paragraph_format.first_line_indent = Inches(-0.3)
            p.paragraph_format.tab_stops.add_tab_stop(Inches(0.35))
            p.alignment = WD_ALIGN_PARAGRAPH.LEFT
            add_text(p, num + ".\t" + re.sub(r"^\d+\.\s+", "", line))
        elif line.startswith("> "):
            para(doc, line[2:].strip(), style="Note")
        else:
            # join wrapped lines into one paragraph
            buf = [line.strip()]
            while i + 1 < len(lines) and lines[i + 1].strip() and not re.match(r"^(#|!\[|Table: |- |\d+\. |> |\[\[|\{\{|%%|\||```|\$\$)", lines[i + 1].strip()):
                i += 1
                buf.append(lines[i].strip())
            para(doc, " ".join(buf))
        i += 1


def load_references():
    path = CONTENT / "references.md"
    if not path.exists():
        return []
    refs = [l.strip()[2:] for l in path.read_text().splitlines() if l.strip().startswith("- ")]
    return sorted(refs, key=lambda r: re.sub(r"[^a-z0-9 ]", "", r.lower()))


# ------------------------------------------------------------------ preliminary pages

def title_page(doc, info):
    for _ in range(3):
        para(doc, "", style="Centered")
    para(doc, info["title"], style="Centered", bold=True, size=16)
    for _ in range(4):
        para(doc, "", style="Centered")
    para(doc, info["student"].upper(), style="Centered", bold=True, size=14)
    para(doc, f"(LCID: {info['lcid']})", style="Centered", size=12)
    for _ in range(4):
        para(doc, "", style="Centered")
    para(doc, "FINAL YEAR PROJECT REPORT SUBMITTED IN PARTIAL FULFILMENT FOR THE DEGREE OF BACHELOR OF INFORMATION TECHNOLOGY (HONOURS)",
         style="Centered", bold=True, size=12)
    for _ in range(3):
        para(doc, "", style="Centered")
    for line in ["FACULTY OF COMPUTER SCIENCE IN MULTIMEDIA", "LINCOLN UNIVERSITY COLLEGE", "MALAYSIA"]:
        para(doc, line, style="Centered", bold=True, size=12)
    for _ in range(2):
        para(doc, "", style="Centered")
    para(doc, info["submission"], style="Centered", bold=True, size=12)


def signature(doc, name, role, date_line=True):
    para(doc, "", before=24)
    para(doc, "............................................................", align=WD_ALIGN_PARAGRAPH.LEFT, after=0)
    para(doc, name, align=WD_ALIGN_PARAGRAPH.LEFT, bold=True, after=0)
    para(doc, role, align=WD_ALIGN_PARAGRAPH.LEFT, after=0)
    if date_line:
        para(doc, "Date: ..............................", align=WD_ALIGN_PARAGRAPH.LEFT, after=6)


def prelim_heading(doc, text):
    doc.add_paragraph(text.upper(), style="Heading 1")


def build(front, chapters_md):
    doc = Document()
    build_styles(doc)
    sec0 = doc.sections[0]
    margins(sec0)
    page_numbers(sec0, None)
    # 1-2 Outer and inner title pages (not numbered)
    title_page(doc, front["info"])
    page_break(doc)
    title_page(doc, front["info"])
    # Roman numbers from the Recommendation page
    sec = doc.add_section(WD_SECTION.NEW_PAGE)
    margins(sec)
    page_numbers(sec, "lowerRoman")
    for block in front["blocks"]:
        kind = block[0]
        if kind == "heading":
            prelim_heading(doc, block[1])
        elif kind == "para":
            para(doc, block[1], style=block[2] if len(block) > 2 else "Normal")
        elif kind == "signoff":
            para(doc, block[1], align=WD_ALIGN_PARAGRAPH.RIGHT, bold=True, before=18)
        elif kind == "signature":
            signature(doc, block[1], block[2], block[3] if len(block) > 3 else True)
        elif kind == "break":
            pass  # the next heading starts a new page
        elif kind == "toc":
            p = doc.add_paragraph()
            field(p, block[1], "Right-click and choose Update Field to build this list.")
        elif kind == "table":
            add_table(doc, block[1], block[2])
        elif kind == "abbrev":
            t = doc.add_table(rows=0, cols=2)
            t.alignment = WD_TABLE_ALIGNMENT.CENTER
            for abbr, meaning in block[1]:
                row = t.add_row().cells
                row[0].width, row[1].width = Inches(1.4), Inches(4.6)
                add_text(row[0].paragraphs[0], abbr, size=12, bold=True)
                add_text(row[1].paragraphs[0], meaning, size=12)
                for c in row:
                    c.paragraphs[0].paragraph_format.line_spacing = 1.15
                    c.paragraphs[0].paragraph_format.space_after = Pt(2)
                    c.paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.LEFT
    refs = load_references()
    render_markdown(doc, chapters_md, refs)
    update_fields_on_open(doc)
    core = doc.core_properties
    core.title = front["info"]["title"]
    core.author = front["info"]["student"]
    core.subject = "Final Year Project Report, Bachelor of Information Technology (Honours), Lincoln University College"
    doc.save(OUT)
    return OUT


if __name__ == "__main__":
    import importlib.util
    spec = importlib.util.spec_from_file_location("front", CONTENT / "front.py")
    front = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(front)
    md = "\n\n".join(p.read_text() for p in sorted(CONTENT.glob("[0-9][0-9]_*.md")))
    out = build({"info": front.INFO, "blocks": front.BLOCKS}, md)
    print("wrote", out.relative_to(HERE.parent.parent))
