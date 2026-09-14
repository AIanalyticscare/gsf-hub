#!/usr/bin/env python3
"""Convert the approved GSF role-guide DOCX files into Docusaurus Markdown."""

from __future__ import annotations

import re
import sys
from pathlib import Path

from docx import Document
from docx.document import Document as DocumentObject
from docx.table import Table
from docx.text.paragraph import Paragraph
from docx.oxml.table import CT_Tbl
from docx.oxml.text.paragraph import CT_P


GUIDES = {
    "GSF-Hub-End-User-Guide.docx": {
        "slug": "end-user-guide",
        "title": "GSF Hub End-User Guide",
        "pdf": "GSF-Hub-End-User-Guide.pdf",
        "images": [
            "end-01-home.png", "end-10-register.png", "end-09-sign-in.png",
            "end-11-account.png", "end-12-learning-signed-in.png",
            "end-16-course-enrolled.png", "end-04-resources.png",
            "end-05-case-studies.png", "end-06-data-centre.png",
            "end-07-directory.png", "end-14-forum-signed-in.png",
            "end-15-forum-new-topic.png",
        ],
    },
    "GSF-Hub-Staff-Content-Operations-Guide.docx": {
        "slug": "staff-content-operations-guide",
        "title": "GSF Hub Staff Content Operations Guide",
        "pdf": "GSF-Hub-Staff-Content-Operations-Guide.pdf",
        "images": [
            "staff-01-newsletter.png", "staff-02-data-centre.png",
            "staff-10-data-indicators.png", "staff-11-data-disaggregation.png",
            "staff-12-data-import-export.png", "staff-03-learn.png",
            "staff-09-upload-course.png", "staff-14-learn-certificates.png",
            "staff-04-resources.png", "staff-07-add-resource.png",
            "staff-05-case-studies.png", "staff-08-add-case-study.png",
            "staff-06-forum.png",
        ],
    },
    "GSF-Hub-Administrator-Guide.docx": {
        "slug": "administrator-guide",
        "title": "GSF Hub Administrator Guide",
        "pdf": "GSF-Hub-Administrator-Guide.pdf",
        "images": ["admin-01-dashboard.png", "admin-03-role-editor.png", "admin-04-plugins.png"],
    },
}


def iter_blocks(document: DocumentObject):
    for child in document.element.body.iterchildren():
        if isinstance(child, CT_P):
            yield Paragraph(child, document)
        elif isinstance(child, CT_Tbl):
            yield Table(child, document)


def clean(value: str) -> str:
    return value.replace("|", "\\|").strip()


def table_markdown(table: Table) -> list[str]:
    rows = [[clean(cell.text) for cell in row.cells] for row in table.rows]
    if not rows or not any(any(cell for cell in row) for row in rows):
        return []
    width = max(len(row) for row in rows)
    if width == 1:
        text = rows[0][0]
        lead, separator, body = text.partition("\n")
        rendered = f"**{lead}.** {body}" if separator and body else f"**{lead}**"
        return [rendered, ""]
    rows = [row + [""] * (width - len(row)) for row in rows]
    lines = ["| " + " | ".join(rows[0]) + " |", "| " + " | ".join(["---"] * width) + " |"]
    lines.extend("| " + " | ".join(row) + " |" for row in rows[1:])
    return lines + [""]


def convert(source: Path, destination: Path, metadata: dict[str, object]) -> None:
    document = Document(source)
    output = [
        "---",
        f"title: {metadata['title']}",
        "---",
        "",
        "import useBaseUrl from '@docusaurus/useBaseUrl';",
        "",
        '<div className="guide-downloads">',
        f'  <a className="button button--primary" href={{useBaseUrl(\'/guides/{metadata["pdf"]}\')}}>Download the approved PDF</a>',
        "</div>",
        "",
    ]
    image_index = 0
    skip_title = True

    for block in iter_blocks(document):
        if isinstance(block, Table):
            output.extend(table_markdown(block))
            continue

        text = block.text.strip()
        style = block.style.name if block.style else "Normal"
        if not text:
            continue
        if style == "Title" and skip_title:
            skip_title = False
            continue
        if text == "PRACTICAL USER GUIDE":
            continue
        if style == "Subtitle":
            output.extend([f"> {text}", ""])
        elif style.startswith("Heading "):
            level = min(int(style.split()[-1]) + 1, 6)
            output.extend([f"{'#' * level} {text}", ""])
        elif style.startswith("List Number"):
            output.append(f"1. {text}")
        elif style.startswith("List Bullet"):
            output.append(f"- {text}")
        elif style == "Caption":
            if image_index < len(metadata["images"]):
                image = metadata["images"][image_index]
                alt = re.sub(r"^Figure\s+\d+\.\s*", "", text)
                output.extend(["", f"![{alt}](/img/guides/{image})", "", f"*{text}*", ""])
                image_index += 1
            else:
                output.extend([f"*{text}*", ""])
        else:
            output.extend([text, ""])

    destination.write_text("\n".join(output).rstrip() + "\n", encoding="utf-8")


def main() -> int:
    if len(sys.argv) != 3:
        print("usage: convert_user_guides_to_markdown.py SOURCE_DIR DESTINATION_DIR", file=sys.stderr)
        return 2
    source_dir = Path(sys.argv[1])
    destination_dir = Path(sys.argv[2])
    destination_dir.mkdir(parents=True, exist_ok=True)
    for filename, metadata in GUIDES.items():
        convert(source_dir / filename, destination_dir / f"{metadata['slug']}.md", metadata)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
