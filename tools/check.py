#!/usr/bin/env python3
"""Lint source and verify package wiring without requiring Magento dependencies."""
from pathlib import Path
import json, re, subprocess, sys, xml.etree.ElementTree as ET
root=Path(__file__).resolve().parents[1]
errors=[]
files=[p for p in root.rglob('*') if p.is_file() and not any(x in p.parts for x in ['.git','vendor'])]
for p in files:
    if p.suffix in ['.php','.phtml']:
        result=subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
        if result.returncode: errors.append(result.stdout+result.stderr)
    if p.suffix=='.xml':
        try: ET.parse(p)
        except ET.ParseError as e: errors.append(f'{p}: {e}')
manifest=json.loads((root/'composer.json').read_text())
assert manifest['type']=='magento2-module'
assert manifest['autoload']['psr-4']=={'ReviewAutomation\\Reviews\\':''}
assert 'version' not in manifest, 'Use Git tags for versions'
for p in files:
    if p.suffix=='.php' and p.parts[-2] not in ['tests','tools'] and p.name!='registration.php':
        source=p.read_text()
        namespace=re.search(r'namespace\s+([^;{]+);',source)
        if not namespace: errors.append(f'{p}: missing namespace'); continue
        expected='ReviewAutomation\\Reviews'
        if p.parent != root: expected+='\\'+str(p.parent.relative_to(root)).replace('/','\\')
        if namespace[1]!=expected: errors.append(f'{p}: namespace mismatch: {namespace[1]} != {expected}')
for p in (root/'view').rglob('*.xml'):
    tree=ET.parse(p)
    for element in tree.iter():
        template=element.attrib.get('template','')
        if template.startswith('ReviewAutomation_Reviews::'):
            area=p.relative_to(root/'view').parts[0]
            if not (root/'view'/area/'templates'/template.split('::')[1]).is_file(): errors.append(f'{p}: missing template {template}')
# Verify schema whitelist tracks the declarative schema exactly.
schema=ET.parse(root/'etc/db_schema.xml')
whitelist=json.loads((root/'etc/db_schema_whitelist.json').read_text())
for table in schema.getroot().findall('table'):
    name=table.attrib['name']
    for kind in ['column','index','constraint']:
        declared={e.attrib['name' if kind=='column' else 'referenceId'] for e in table.findall(kind)}
        if declared != set(whitelist[name].get(kind,{})): errors.append(f'{name}: {kind} whitelist mismatch')
defaults=ET.parse(root/'etc/config.xml').getroot().find('default/reviewautomation_review_automation')
assert defaults.findtext('general/enabled')=='0'
assert not defaults.findtext('filters/allowed_store_ids')
for name in ['Click','Go']:
    source=(root/f'Controller/Response/{name}.php').read_text()
    if name=='Click' and ('->save(' in source or '->setStatus(' in source): errors.append('GET click must not mutate sentiment')
for p in [root/'Controller/Response/Submit.php',root/'Controller/Unsubscribe/Save.php']:
    assert 'HttpPostActionInterface' in p.read_text()
assert '->suppress(' not in (root/'Controller/Unsubscribe/Index.php').read_text()
for p in (root/'view/frontend/email').glob('*.html'):
    source=p.read_text()
    if re.search(r'vanimal|4x4at|reviewautomation\.com|prize draw|van racking',source,re.I): errors.append(f'{p}: client-specific copy')
from check_emails import check as check_emails
try:
    print(check_emails())
except (AssertionError, subprocess.CalledProcessError, ValueError) as error:
    errors.append(str(error))
if errors:
    print('\n'.join(errors));sys.exit(1)
print(f'Package checks passed: {len(files)} files, PHP syntax, XML, namespaces, templates, schema and safe defaults.')
