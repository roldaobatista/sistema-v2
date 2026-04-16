import xml.etree.ElementTree as ET
from collections import defaultdict
import re

tree = ET.parse('report-2.xml')
root = tree.getroot()

failures = []

for testcase in root.iter('testcase'):
    failure = testcase.find('failure')
    error = testcase.find('error')

    node = failure if failure is not None else error
    if node is not None:
        message = node.get('message', '') or node.text or ''
        className = testcase.get('class', '')
        name = testcase.get('name', '')
        failures.append({'class': className, 'name': name, 'message': message})

grouped = defaultdict(list)
for f in failures:
    msg_lines = f['message'].strip().split('\n')
    summary = msg_lines[0] if msg_lines else "Unknown"

    if 'Unknown column' in summary or 'Column not found' in summary or '42S22' in summary:
        col = re.search(r"Unknown column '(.+?)'", summary)
        cat = f"Missing column: {col.group(1)}" if col else "Missing column"
        grouped[cat].append({'class': f['class'], 'name': f['name'], 'msg': summary})
    elif 'Base table or view not found' in summary:
        grouped['Missing table'].append({'class': f['class'], 'name': f['name'], 'msg': summary})
    elif 'Undefined method' in summary:
        grouped['Undefined method'].append({'class': f['class'], 'name': f['name'], 'msg': summary})
    elif 'Failed asserting that' in summary:
        grouped['Assertion Failed'].append({'class': f['class'], 'name': f['name'], 'msg': summary})
    elif '404 Not Found' in summary or 'Expected response status code [200] but received 404' in summary:
        grouped['404 Not Found'].append({'class': f['class'], 'name': f['name'], 'msg': summary})
    else:
        grouped[summary[:80]].append({'class': f['class'], 'name': f['name'], 'msg': summary})

with open('failed_tests.md', 'w', encoding='utf-8') as f:
    f.write(f"# Total structured failures: {len(failures)}\n\n")
    for group, tests in sorted(grouped.items(), key=lambda x: len(x[1]), reverse=True):
        f.write(f"## [{len(tests)}x] {group}\n")

        classes = defaultdict(list)
        for t in tests:
            classes[t['class']].append(t)

        for c, t_list in classes.items():
            f.write(f"### {c}\n")
            for t in t_list:
                f.write(f"  - `{t['name']}`\n")
                if group not in ['Assertion Failed', '404 Not Found'] and 'Missing column' not in group:
                    f.write(f"    > {t['msg'][:120]}...\n")
        f.write("\n")
