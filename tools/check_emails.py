#!/usr/bin/env python3
"""Validate complete emails with the actual PHP row renderers, without sending mail."""
from html.parser import HTMLParser
from pathlib import Path
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
VOID = {'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'}


class EmailHTML(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.stack = []
        self.errors = []
        self.links = []

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        parent = self.stack[-1][0] if self.stack else None
        if tag == 'tr' and parent not in {'table', 'tbody', 'thead', 'tfoot'}:
            self.errors.append(f'tr nested in {parent}; email clients will move content')
        if tag in {'td', 'th'} and parent != 'tr':
            self.errors.append(f'{tag} nested in {parent}')
        if parent in {'table', 'tbody', 'thead', 'tfoot'} and tag not in {'tr', 'tbody', 'thead', 'tfoot', 'caption', 'colgroup'}:
            self.errors.append(f'{tag} directly inside {parent}')
        if parent == 'tr' and tag not in {'td', 'th'}:
            self.errors.append(f'{tag} directly inside tr')
        if tag == 'a':
            self.links.append(attrs.get('href', ''))
            if not any('email-card' in a.get('class', '').split() for _, a in self.stack):
                self.errors.append('customer link escaped the email card')
        if tag == 'script':
            self.errors.append('unescaped product markup')
        if tag not in VOID:
            self.stack.append((tag, attrs))

    def handle_endtag(self, tag):
        if tag in VOID:
            return
        if not self.stack or self.stack[-1][0] != tag:
            self.errors.append(f'unbalanced closing {tag}')
        else:
            self.stack.pop()

    def handle_data(self, data):
        if data.strip() and self.stack and self.stack[-1][0] in {'table', 'tbody', 'thead', 'tfoot', 'tr'}:
            self.errors.append('text directly inside table/row')


def validate(html):
    parser = EmailHTML()
    parser.feed(html)
    parser.close()
    if parser.stack:
        parser.errors.append('unclosed elements')
    return parser


def check():
    result = subprocess.run(['php', str(ROOT / 'tests/render-emails.php')], capture_output=True, text=True, check=True)
    emails = json.loads(result.stdout)
    errors = []
    # This reproduces the original row-in-cell bug, and must fail validation.
    broken = '<table class="email-card"><tr><td>Intro<tr><td>Order</td></tr><a href="https://example.com">Feedback</a></td></tr></table>'
    assert validate(broken).errors, 'Validator must catch the original email layout regression'
    for name, rendered in emails.items():
        html = rendered['html']
        parsed = validate(html)
        errors.extend(f'{name}: {error}' for error in parsed.errors)
        assert 'https://example.com/unsubscribe' in parsed.links, name + ': missing unsubscribe'
        assert 'Example Store' in html, name + ': missing store branding'
        assert '__' not in html, name + ': unresolved placeholder'
        if name.endswith('-logo'):
            assert 'width="220"' in html and 'width=440&amp;height=80' in html, name + ': logo sizing/escaping'
        if name.startswith('satisfaction'):
            assert len(parsed.links) == 4, name + ': missing feedback/public/unsubscribe link'
        if name.startswith(('satisfaction', 'product')) and not name.endswith(('-empty', '-preview')):
            assert '&lt;script&gt;not markup&lt;/script&gt;' in html, name + ': product escaping'
        assert 'Alex & Sam' in rendered['subject'] or name.startswith('review_request_nudge'), name + ': subject escaping'
    if errors:
        raise AssertionError('\n'.join(errors))
    return f'Email rendering checks passed: {len(emails)} complete emails, real row fragments, table structure, link containment, escaping and logo sizing.'


if __name__ == '__main__':
    print(check())
