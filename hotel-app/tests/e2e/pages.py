from lib import *
b = staff('owner@demo.test')
pages = ['/staff', '/staff/tables', '/staff/review', '/staff/cashier', '/staff/cash', '/staff/refunds', '/staff/availability',
         '/admin/meals', '/admin/ingredients', '/admin/media', '/admin/reports', '/admin/audit', '/admin/operations', '/admin/devices',
         '/admin/staff', '/admin/settings', '/kitchen', '/collection', '/admin/reports/export/payments', '/admin/reports/export/sales']
for p in pages:
    r = b.get(p)
    ok = r.status_code == 200
    print(('OK  ' if ok else 'ERR ') + str(r.status_code) + ' ' + p + ('' if ok else ' ' + r.url))
    check(ok, p) if not ok else None
    if not ok:
        m = re.search(r'<title>(.*?)</title>', r.text, re.S)
        print('     ', (m.group(1) if m else r.text[:300]).strip()[:300])

# Admin forms
r = b.get('/admin/settings')
ver = re.search(r'name="expected_version" value="(\d+)"', r.text).group(1)
r = b.post('/admin/settings', {'expected_version': ver, 'name': 'Demo Lakeside Hotel', 'timezone': 'Africa/Nairobi', 'business_day_cutoff': '04:00'})
check(flash(r)[0] == 'Settings updated.', f'settings saved {flash(r)}')
ver = re.search(r'name="expected_version" value="(\d+)"', r.text).group(1)
r = b.post('/admin/settings/receipt', {'expected_version': ver, 'receipt_header': 'P.O. Box 1, Kisii', 'receipt_footer': 'Asante sana!'})
check(flash(r)[0] == 'Receipt identity updated.', f'receipt identity saved {flash(r)}')
r = b.post('/admin/settings/receipt', {'expected_version': ver, 'receipt_header': 'stale'})
check('changed elsewhere' in (flash(r)[0] or ''), f'stale settings refused {flash(r)}')
r = b.post('/admin/settings/commerce', {'tax_rate': '16', 'tax_label': 'VAT', 'kra_pin': 'P051234567X', 'kiosk_payment_minutes': '10'})
check(flash(r)[0] == 'Tax and payment settings saved.', f'commerce saved {flash(r)}')
import uuid as _u
lbl = 'Terrace ' + _u.uuid4().hex[:4]
r = b.post('/admin/settings/tables', {'label': lbl})
check(flash(r)[0] == 'Table configuration updated.' and lbl in r.text, f'table added {flash(r)}')
em = 'new-' + _u.uuid4().hex[:6] + '@demo.test'
r = b.post('/admin/staff', {'email': em, 'name': 'New Waiter', 'password': 'a-long-password-123', 'roles[]': ['waiter']})
check(em in r.text and not flash(r)[1], f'staff created {flash(r)}')
n = Browser(); n.get('/staff/sign-in'); r = n.post('/staff/sign-in', {'email': em, 'password': 'a-long-password-123'})
check('sign-in' not in r.url, 'new staff can sign in')
r = n.get('/admin/settings')
check(r.status_code == 403, f'waiter cannot open settings ({r.status_code})')
