import os
import re
import json

DOCS_DIR = r"c:\PROJETOS\sistema\docs"

def audit_docs():
    findings = {
        "empty_sections": [],
        "todos_fixmes": [],
        "unchecked_tasks": [],
        "missing_mandatory_sections": []
    }

    mandatory_sections = ["Regras de Negocio", "Checklist de Implementacao", "Modelos"]

    for root, dirs, files in os.walk(DOCS_DIR):
        for file in files:
            if not file.endswith(".md"):
                continue

            filepath = os.path.join(root, file)
            with open(filepath, "r", encoding="utf-8") as f:
                content = f.read()
                lines = content.split('\n')

                # Check TODO/FIXME (case sensitive to avoid portuguese "todo")
                for i, line in enumerate(lines):
                    if "TODO" in line or "FIXME" in line:
                        findings["todos_fixmes"].append(f"{file}:{i+1} -> {line.strip()}")
                    if "- [ ]" in line:
                        findings["unchecked_tasks"].append(f"{file}:{i+1} -> {line.strip()}")

                # Find empty sections (a header followed immediately by another header or EOF)
                headers = []
                for i, line in enumerate(lines):
                    if line.startswith("#"):
                        headers.append((i, line))

                for idx in range(len(headers)):
                    line_idx, header_text = headers[idx]

                    # Next header
                    if idx + 1 < len(headers):
                        next_line_idx = headers[idx+1][0]
                    else:
                        next_line_idx = len(lines)

                    # Calculate content length
                    section_content = "\n".join(lines[line_idx+1:next_line_idx]).strip()
                    if len(section_content) == 0 or section_content == "Em breve." or "em desenvolvimento" in section_content.lower():
                        findings["empty_sections"].append(f"{file}: {header_text.strip()}")

                # For module files, check mandatory sections
                if "modules" in root:
                    for ms in mandatory_sections:
                        # normalize case and accents
                        if ms.replace(" ", "").lower() not in content.replace(" ", "").replace("ç", "c").replace("ã", "a").lower():
                            findings["missing_mandatory_sections"].append(f"{file} missing section: {ms}")

    with open(r"c:\PROJETOS\sistema\docs\auditoria\audit_results.json", "w") as f:
        json.dump(findings, f, indent=4)

    print(f"Audit completed. Found {len(findings['todos_fixmes'])} TODOs/FIXMEs.")
    print(f"Found {len(findings['unchecked_tasks'])} unchecked tasks.")
    print(f"Found {len(findings['empty_sections'])} empty sections.")
    print(f"Found {len(findings['missing_mandatory_sections'])} missing mandatory sections in modules.")

if __name__ == "__main__":
    audit_docs()
