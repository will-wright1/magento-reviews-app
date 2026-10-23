#!/usr/bin/env python3
"""Check complete landing markup and the no-persistence preview boundary."""
from html.parser import HTMLParser
from pathlib import Path
import json
import subprocess
ROOT=Path(__file__).resolve().parents[1]
class Landing(HTMLParser):
    def __init__(self):
        super().__init__();self.headings=0;self.forms=[];self.links=[];self.controls=[]
    def handle_starttag(self,tag,attrs):
        attrs=dict(attrs)
        if tag=='h1': self.headings+=1
        if tag=='form': self.forms.append(attrs)
        if tag=='a': self.links.append(attrs.get('href',''))
        if tag in ['input','textarea','select']: self.controls.append(attrs)
def check():
    pages=json.loads(subprocess.run(['php',str(ROOT/'tests/render-pages.php')],capture_output=True,text=True,check=True).stdout)
    for name,html in pages.items():
        page=Landing();page.feed(html)
        assert page.headings==1,name+': one page heading required'
        assert 'Back to Review Automation' not in html and '<img' not in html,name+': duplicate/module branding'
        if name.startswith('preview-'):
            assert 'Test email preview' in html,name+': missing test notice'
            assert all('/response/' not in url and '/unsubscribe/' not in url for url in page.links),name+': preview must not enter customer routes'
            assert all(form.get('method')=='get' and '/preview/' in form['action'] for form in page.forms),name+': preview form must stay in read-only route'
            assert all(not control.get('name') for control in page.controls),name+': entered sample text must never be transmitted'
        elif name in ['confirm','feedback','unsubscribe']:
            assert page.forms[0]['method']=='post',name+': production mutation still requires POST'
            assert any(c.get('name')=='form_key' for c in page.controls),name+': CSRF retained'
    controller=(ROOT/'Controller/Preview/Index.php').read_text()
    assert 'HttpGetActionInterface' in controller
    assert 'NOINDEX,NOFOLLOW' in (ROOT/'view/frontend/layout/reviewautomation_reviews_shared.xml').read_text()
    assert all(term not in controller for term in ['->save(', '->dispatch(', '->suppress(', 'TokenLookup', 'QueueFactory']), 'Preview must not access customer actions/data'
    for name in ['expired','click','feedback','feedbackthankyou','thankyou']:
        assert 'reviewautomation_reviews_shared' in (ROOT/f'view/frontend/layout/reviewautomation_reviews_response_{name}.xml').read_text()
    assert 'remove="true"' in (ROOT/'view/frontend/layout/reviewautomation_reviews_shared.xml').read_text()
    return f'Landing checks passed: {len(pages)} real template renders, one heading, POST/CSRF retained and isolated sample-only previews.'
if __name__=='__main__':print(check())
