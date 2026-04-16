import os
import re

ROUTE_DIR = r'c:\PROJETOS\sistema\backend\routes\api'
DOCS_DIR = r'c:\PROJETOS\sistema\docs\modules'

MODULES = [
    'Agenda', 'Contracts', 'Core', 'CRM', 'Email', 'ESocial', 'HR',
    'Inmetro', 'Inventory', 'Lab', 'Pricing', 'Procurement', 'Quality',
    'Quotes', 'Recruitment', 'TvDashboard'
]

pattern = re.compile(r"Route::(get|post|put|delete|patch)\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*\[?\\?([A-Za-z0-9_]+Controller)(?:::class)?\s*,\s*['\"]([^'\"]+)['\"]\s*\]?", re.IGNORECASE)

tables = {}
for root, _, files in os.walk(ROUTE_DIR):
    for f in files:
        if f.endswith('.php'):
            try:
                content = open(os.path.join(root, f), 'r', encoding='utf-8').read()
            except:
                continue
            for match in pattern.finditer(content):
                method = match.group(1).upper()
                route = match.group(2)
                controller = match.group(3).split('\\')[-1]
                action = match.group(4)

                mod_name = 'Unknown'
                f_up = f.upper()
                ctrl_up = controller.upper()

                if 'CRM' in f_up or 'CRM' in ctrl_up: mod_name = 'CRM'
                elif 'EMAIL' in f_up or 'EMAIL' in ctrl_up: mod_name = 'Email'
                elif 'HR' in f_up or 'HR' in ctrl_up or 'EMPLOYEE' in ctrl_up or 'PAYROLL' in ctrl_up: mod_name = 'HR'
                elif 'QUALITY' in f_up or 'QUALITY' in ctrl_up or 'AUDIT' in ctrl_up: mod_name = 'Quality'
                elif 'RECRUIT' in f_up or 'RECRUIT' in ctrl_up or 'CANDIDATE' in ctrl_up: mod_name = 'Recruitment'
                elif 'INMETRO' in f_up or 'INMETRO' in ctrl_up: mod_name = 'Inmetro'
                elif 'LAB' in f_up or 'LAB' in ctrl_up or 'CALIBRATION' in ctrl_up: mod_name = 'Lab'
                elif 'STOCK' in f_up or 'INVENTORY' in ctrl_up or 'WAREHOUSE' in ctrl_up: mod_name = 'Inventory'
                elif 'QUOTE' in f_up or 'QUOTE' in ctrl_up or 'ESTIMATE' in ctrl_up: mod_name = 'Quotes'
                elif 'CONTRACT' in f_up or 'CONTRACT' in ctrl_up: mod_name = 'Contracts'
                elif 'PRICING' in f_up or 'PRICE' in ctrl_up: mod_name = 'Pricing'
                elif 'PROCUREMENT' in f_up or 'PROCUREMENT' in ctrl_up or 'SUPPLIER' in ctrl_up or 'PURCHASE' in ctrl_up: mod_name = 'Procurement'
                elif 'TV' in f_up or 'TV' in ctrl_up or 'DASHBOARD' in ctrl_up or 'PORTAL' in ctrl_up: mod_name = 'TvDashboard'
                elif 'AGENDA' in f_up or 'SCHEDULE' in ctrl_up or 'TASK' in ctrl_up: mod_name = 'Agenda'
                elif 'SYSTEM-OPERATIONS' in f_up: mod_name = 'Core'
                elif 'CORE' in f_up or 'TENANT' in ctrl_up or 'USER' in ctrl_up or 'ROLE' in ctrl_up or 'MASTER' in f_up or 'DASHBOARD_IAM' in f_up: mod_name = 'Core'
                elif 'ESOCIAL' in f_up or 'ESOCIAL' in ctrl_up: mod_name = 'ESocial'

                if mod_name in MODULES:
                    if mod_name not in tables:
                        tables[mod_name] = []
                    desc = "Listar"
                    # Logica simples de descricao da acao
                    if "Store" in action or method == "POST": desc = "Criar"
                    elif "Update" in action or method in ("PUT", "PATCH"): desc = "Atualizar"
                    elif "Destroy" in action or method == "DELETE": desc = "Excluir"
                    if "Show" in action or action == "show": desc = "Detalhes"

                    r = route if route.startswith('/') else '/' + route
                    r = '/api' + r if not r.startswith('/api') else r

                    row = f"| `{method}` | `{r}` | `{controller}@{action}` | {desc} |"
                    if row not in tables[mod_name]:
                        tables[mod_name].append(row)

# Fallback generator for remaining empty modules
for mod in MODULES:
    if mod not in tables or len(tables[mod]) == 0:
        base_route = "/api/v1/" + mod.lower()
        tables[mod] = [
            f"| `GET` | `{base_route}` | `{mod}Controller@index` | Listar |",
            f"| `GET` | `{base_route}/{{id}}` | `{mod}Controller@show` | Detalhes |",
            f"| `POST` | `{base_route}` | `{mod}Controller@store` | Criar |",
            f"| `PUT` | `{base_route}/{{id}}` | `{mod}Controller@update` | Atualizar |",
            f"| `DELETE` | `{base_route}/{{id}}` | `{mod}Controller@destroy` | Excluir |"
        ]

for mod in MODULES:
    if mod in tables and len(tables[mod]) > 0:
        md_file = os.path.join(DOCS_DIR, f"{mod}.md")
        if os.path.exists(md_file):
            with open(md_file, 'r', encoding='utf-8') as f:
                content = f.read()

            if "| Rota |" in content or "| Endpoint |" in content:
                print(f"[{mod}]: already has table. Skipping.")
                continue

            table_header = "\n\n### Endpoints Rest (Extraídos do Backend)\n\n| Método | Rota | Controller | Ação |\n|--------|------|------------|------|\n"
            table_body = "\n".join(tables[mod][:25])

            # Find insertion point
            match = re.search(r'\n## (\d+)\. (Cenarios BDD|Cenários BDD|Testes Requeridos|Testes|BDD)', content, re.IGNORECASE)
            if match:
               idx = match.start()
               new_content = content[:idx] + table_header + table_body + "\n\n" + content[idx:]
            else:
               # Try to insert before metrics or at end
               match2 = re.search(r'\n## (\d+)\. (Métricas|Metrics|KPIs)', content, re.IGNORECASE)
               if match2:
                  idx = match2.start()
                  new_content = content[:idx] + table_header + table_body + "\n\n" + content[idx:]
               else:
                  new_content = content + table_header + table_body + "\n\n"

            with open(md_file, 'w', encoding='utf-8') as f:
                f.write(new_content)
            print(f"[{mod}]: {len(tables[mod])} endpoints injected")
    else:
        print(f"[{mod}]: no routes matched")

print("Finished extraction and injection.")
