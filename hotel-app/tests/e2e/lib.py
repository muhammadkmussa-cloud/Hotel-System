import re, sys, json, requests

BASE = sys.argv[1] if len(sys.argv) > 1 and sys.argv[1].startswith('http') else 'http://127.0.0.1:8000'
PASSWORD = 'demo-password-2026'

def token(html):
    m = re.search(r'name="csrf-token" content="([^"]+)"', html) or re.search(r'name="_token" value="([^"]+)"', html)
    return m.group(1) if m else None

class Browser:
    def __init__(self):
        self.s = requests.Session()
        self.csrf = None
        self.last = None
    def follow(self, r):
        self.unsecure()
        n = 0
        while r.is_redirect and n < 10:
            loc = r.headers['Location']
            if loc.startswith('/'): loc = BASE + loc
            r = self.s.get(loc, allow_redirects=False)
            self.unsecure()
            n += 1
        return r
    def get(self, path, **kw):
        r = self.follow(self.s.get(BASE + path, allow_redirects=False, **kw))
        t = token(r.text)
        if t: self.csrf = t
        self.last = r
        self.unsecure()
        return r
    def unsecure(self):
        for c in self.s.cookies:
            c.secure = False
    def post(self, path, data=None, **kw):
        if self.csrf is None: self.get('/staff/sign-in')
        d = dict(data or {}); d['_token'] = self.csrf
        r = self.follow(self.s.post(BASE + path, data=d, allow_redirects=False, **kw))
        t = token(r.text)
        if t: self.csrf = t
        self.last = r
        self.unsecure()
        return r
    def api(self, method, path, body=None, headers=None):
        if self.csrf is None: self.get('/device/pair')
        h = {'Accept': 'application/json', 'X-CSRF-TOKEN': self.csrf}
        if body is not None: h['Content-Type'] = 'application/json'
        h.update(headers or {})
        r = self.s.request(method, BASE + '/api/v1' + path, data=None if body is None else json.dumps(body), headers=h)
        self.unsecure()
        try: j = r.json()
        except Exception: j = None
        return r.status_code, j

def staff(email):
    b = Browser()
    b.get('/staff/sign-in')
    r = b.post('/staff/sign-in', {'email': email, 'password': PASSWORD})
    assert 'sign-in' not in r.url, f'sign-in failed for {email}: {r.url}'
    return b

def flash(r):
    m = re.search(r'class="notice notice-ok" role="status">([^<]+)<', r.text)
    m2 = re.search(r'notice-error.*?<ul>(.*?)</ul>', r.text, re.S)
    e = re.findall(r'<li>([^<]+)</li>', m2.group(1)) if m2 else []
    return (m.group(1).strip() if m else None), e

def check(cond, msg):
    print(('PASS ' if cond else 'FAIL ') + msg)
    if not cond:
        global failures
        failures += 1
failures = 0


def pair(owner, mode, name):
    """Enrol a device as the owner and pair a fresh browser with its code."""
    r = owner.post('/admin/devices/enroll', {'name': name, 'mode': mode})
    code = re.search(r'letter-spacing:.15em[^>]*>([A-Z0-9]{4}-[A-Z0-9]{4})<', r.text).group(1)
    d = Browser(); d.get('/device/pair')
    r = d.post('/device/pair', {'code': code})
    return d, r


def ids(pattern, text):
    return re.findall(pattern, text)


import atexit as _atexit, os as _os
def _summary():
    print(f'-- {failures} failure(s)')
    if failures:
        _os._exit(1)
_atexit.register(_summary)
