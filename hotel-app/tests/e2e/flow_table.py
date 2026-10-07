# End-to-end: waiter opens table, tablet pairs + binds, guest orders, kitchen
# cooks, guest pays by M-PESA simulator, second guest pays cash, visit closes.
from lib import *
import uuid as U

owner = staff('owner@demo.test')
waiter = staff('waiter@demo.test')

def pair(mode, name):
    r = owner.post('/admin/devices/enroll', {'name': name, 'mode': mode})
    code = re.search(r'letter-spacing:.15em[^>]*>([A-Z0-9]{4}-[A-Z0-9]{4})<', r.text).group(1)
    d = Browser(); d.get('/device/pair')
    r = d.post('/device/pair', {'code': code})
    return d, r

TABNAME = 'E2E tablet ' + U.uuid4().hex[:6]
tab, r = pair('tablet', TABNAME)
check(r.url.endswith('/table/waiting'), f'unbound tablet lands on waiting page ({r.url})')
st, j = tab.api('GET', '/table/context')
check(st == 401 and j['error']['code'] == 'GUEST_BINDING_REQUIRED', 'API refuses unbound tablet')

# Waiter opens Table 1 (pick first free table)
r = waiter.get('/staff/tables')
tid = re.search(r'name="table_id" value="([0-9a-f-]{36})"', r.text).group(1)
r = waiter.post('/staff/visits/open', {'table_id': tid})
vid = re.findall(r'/staff/visits/([0-9a-f-]{36})', r.text)[-1] if '/staff/visits/' in r.text else None
check(vid is not None, 'visit opened')
r = waiter.post(f'/staff/visits/{vid}/guests', {'name': 'Alice'})
r = waiter.post(f'/staff/visits/{vid}/guests', {'name': 'Bob'})
gids = re.findall(r'name="guest_id" value="([0-9a-f-]{36})"', r.text)
check(len(gids) == 2, f'two guests added ({len(gids)})')
sess = re.findall(r'<option value="([0-9a-f-]{36})"[^>]*>[^<]*' + re.escape(TABNAME), r.text)
check(len(sess) >= 1, 'tablet session bindable')
r = waiter.post('/staff/guest-bindings', {'guest_id': gids[0], 'device_session_id': sess[0]})
print('   bind:', flash(r))

st, ctx = tab.api('GET', '/table/context')
check(st == 200, f'bound tablet context {st}')
ctx = ctx['data']; print('   ', ctx['table'], ctx['guest'], 'mates', ctx['tablemates'])
st, menu = tab.api('GET', '/table/menu'); menu = menu['data']
check(len(menu['meals']) == 10, f"menu has {len(menu['meals'])} meals")
burger = next(m for m in menu['meals'] if m['name'] == 'Classic cheeseburger')
st, meal = tab.api('GET', f"/table/meals/{burger['id']}"); meal = meal['data']
pickles = next(i for i in meal['ingredients'] if i['name'] == 'Pickles')['id']
bacon = next(i for i in meal['ingredients'] if i['name'] == 'Bacon')['id']
st, cart = tab.api('POST', '/table/cart/lines', {'mealId': burger['id'], 'quantity': 2, 'removed': [pickles], 'extras': [bacon], 'note': 'medium'})
check(st == 200 and cart['data']['totalMinor'] == 2 * (115000 + 15000), f"cart total {cart and cart.get('data', {}).get('total')} st={st} {cart if st != 200 else ''}")
chai = next(m for m in menu['meals'] if m['name'] == 'Kenyan chai')
st, cart = tab.api('POST', '/table/cart/lines', {'mealId': chai['id'], 'quantity': 1, 'removed': [], 'extras': []})
cart = cart['data']
line = next(l for l in cart['lines'] if l['name'] == 'Kenyan chai')
st, cart = tab.api('PATCH', f"/table/cart/lines/{line['id']}", {'quantity': 2}); cart = cart['data']
check(cart['count'] == 4, f"cart count {cart['count']}")
key = str(U.uuid4())
st, sub = tab.api('POST', '/table/orders', {'quoteDigest': cart['digest'], 'allergyNote': None}, {'Idempotency-Key': key})
check(st == 201, f'order submitted {st} {sub if st != 201 else ""}')
st2, sub2 = tab.api('POST', '/table/orders', {'quoteDigest': cart['digest'], 'allergyNote': None}, {'Idempotency-Key': key})
check(st2 == 200 and sub2['data'].get('replayed'), f'idempotent replay {st2}')
st, o = tab.api('GET', '/table/orders')
check(st == 200 and len(o['data']['orders']) == 1, 'orders list shows one submission')

# Kitchen device
kit, r = pair('kitchen', 'E2E kitchen ' + U.uuid4().hex[:4])
check(r.url.endswith('/kitchen'), 'kitchen device lands on board')
st, board = kit.api('GET', '/kitchen/tasks')
tickets = [t for t in board['data']['tickets'] if ctx['table'] in t['label'] and t['state'] not in ('served', 'cancelled')]
check(len(tickets) == 2, f'two station tickets (kitchen+bar): {len(tickets)}')
for t in tickets:
    v = t['version']
    for to in ['preparing', 'ready', 'served']:
        st, res = kit.api('POST', f"/kitchen/tickets/{t['id']}/transitions", {'to': to, 'version': v})
        check(st == 200, f"ticket -> {to} {st} {res if st != 200 else ''}")
        v = res['data']['version'] if st == 200 else v

# Service request from guest
st, _ = tab.api('POST', '/table/service-requests', {'kind': 'call_waiter', 'note': 'Extra napkins'})
check(st == 201, 'service request created')
r = waiter.get(f'/staff/visits/{vid}')
check('Extra napkins' in r.text, 'waiter sees request on visit page')
rid = re.search(r'/staff/service-requests/([0-9a-f-]{36})/resolved', r.text).group(1)
r = waiter.post(f'/staff/service-requests/{rid}/resolved', {'resolution': 'Brought napkins'})
check(flash(r)[0] == 'Request resolved.', f'resolve request {flash(r)}')

# Bill & M-PESA (simulator)
st, bill = tab.api('GET', '/table/bill'); bill = bill['data']
check(bill['balanceMinor'] == 260000 + 40000, f"bill balance {bill['balance']}")
st, co = tab.api('POST', '/table/checkouts')
check(st == 201, f'checkout started {st} {co if st != 201 else ""}')
co = co['data']
st, att = tab.api('POST', f"/table/checkouts/{co['id']}/mpesa-attempts", {'phone': '0712 345 678'})
check(st == 201, f'mpesa attempt {st} {att if st != 201 else ""}')
import time
for _ in range(10):
    time.sleep(2)
    st, a = tab.api('GET', f"/table/payment-attempts/{att['data']['id']}")
    if st != 200: print('   attempt poll', st, a); break
    if a['data']['state'] != 'pending': break
check(a['data']['state'] == 'succeeded', f"mpesa result {a['data']['state']}: {a['data']['message']}")
st, bill = tab.api('GET', '/table/bill'); bill = bill['data']
check(bill['balanceMinor'] == 0 and bill['lastReceipt'], f"bill paid; receipt {bill.get('lastReceipt')}")
st, rc = tab.api('GET', f"/table/receipts/{bill['lastReceipt']['checkoutId']}")
check(st == 200 and rc['data']['total'] == 'KSh 3,000.00', f"receipt total {rc['data'].get('total') if rc else st}")

# Visit cannot close while guest 2 has no orders? (should be fine) — close
r = waiter.get(f'/staff/visits/{vid}')
ver = re.search(r'name="expected_version" value="(\d+)"', r.text).group(1)
r = waiter.post(f'/staff/visits/{vid}/close', {'expected_version': ver})
print('   close:', r.status_code, flash(r), r.url, re.search(r'<title>(.*?)</title>', r.text, re.S).group(1)[:200] if '<title>' in r.text else r.text[:300])
check(r.url.endswith('/staff/tables'), 'visit closed')
st, j = tab.api('GET', '/table/context')
check(st == 401, 'tablet unbound after close')
