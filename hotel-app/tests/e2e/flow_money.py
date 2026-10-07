# Money flows: drawer, allergy review hold, shared dish split, discount,
# cash + card checkout at the cashier, refund approval, kiosk cashier + M-PESA.
from lib import *
import lib, time, uuid as U

owner = staff('owner@demo.test'); waiter = staff('waiter@demo.test')
manager = staff('manager@demo.test'); cashier = staff('cashier@demo.test')

# Drawer
r = cashier.get('/staff/cash')
if 'name="counted"' not in r.text:
    r = cashier.post('/staff/cash/drawers', {'float': '2000'})
    check(flash(r)[0] == 'Drawer opened.', f'drawer opened {flash(r)}')

TAB = 'E2E money tab ' + U.uuid4().hex[:6]
tab, r = pair(owner, 'tablet', TAB)
r = waiter.get('/staff/tables')
tid = ids(r'name="table_id" value="([0-9a-f-]{36})"', r.text)[0]
r = waiter.post('/staff/visits/open', {'table_id': tid})
vid = ids(r'/staff/visits/([0-9a-f-]{36})', r.text)[-1]
waiter.post(f'/staff/visits/{vid}/guests', {'name': 'Carol'})
r = waiter.post(f'/staff/visits/{vid}/guests', {'name': 'Dan'})
g1, g2 = ids(r'name="guest_id" value="([0-9a-f-]{36})"', r.text)[:2]
sess = ids(r'<option value="([0-9a-f-]{36})"[^>]*>[^<]*' + re.escape(TAB), r.text)[0]
waiter.post('/staff/guest-bindings', {'guest_id': g1, 'device_session_id': sess})

st, menu = tab.api('GET', '/table/menu')
pizza = next(m for m in menu['data']['meals'] if m['name'] == 'Chicken pilau')
st, cart = tab.api('POST', '/table/cart/lines', {'mealId': pizza['id'], 'quantity': 1, 'removed': [], 'extras': []})
st, sub = tab.api('POST', '/table/orders', {'quoteDigest': cart['data']['digest'], 'allergyNote': 'Peanut allergy — please check'}, {'Idempotency-Key': str(U.uuid4())})
check(st == 201 and sub['data']['state'] == 'review_hold', f"allergy note holds order: {st} {sub['data'].get('state') if st == 201 else sub}")

# Manager approves review
r = manager.get('/staff/review')
m = re.search(r'/staff/review/([0-9a-f-]{36})/approve".*?name="version" value="(\d+)"', r.text, re.S)
check(m is not None, 'review queue shows held order')
r = manager.post(f'/staff/review/{m.group(1)}/approve', {'version': m.group(2), 'note': 'Checked with kitchen'})
check(flash(r)[0] == 'Order approved and released.', f'approve {flash(r)}')

# Split the pizza between both guests
r = waiter.get(f'/staff/visits/{vid}')
charge = ids(r'/staff/charges/([0-9a-f-]{36})/split', r.text)
check(len(charge) == 1, 'split form on visit page')
r = waiter.post(f'/staff/charges/{charge[0]}/split', {'guest_ids[]': [g1, g2]})
check(flash(r)[0] == 'Dish split between 2 guests.', f'split {flash(r)}')
st, bill = tab.api('GET', '/table/bill')
half = pizza['priceMinor'] // 2
check(bill['data']['balanceMinor'] == half, f"guest 1 owes half: {bill['data']['balanceMinor']} vs {half}")

# Manager discount on guest 2's share
r = manager.get(f'/staff/visits/{vid}')
allocs = ids(r'/staff/allocations/([0-9a-f-]{36})/discount', r.text)
check(len(allocs) == 2, f'two allocations discountable ({len(allocs)})')
r = manager.post(f'/staff/allocations/{allocs[-1]}/discount', {'amount': '100', 'reason': 'Slow service'})
check(flash(r)[0] and flash(r)[0].startswith('Discount of KSh 100.00'), f'discount {flash(r)}')
# Waiter cannot discount
r = waiter.post(f'/staff/allocations/{allocs[0]}/discount', {'amount': '100', 'reason': 'x'})
check(r.status_code == 403 or 'not allowed' in r.text.lower() or flash(r)[1], f'waiter discount refused ({r.status_code})')

# Cashier: guest 1 cash, guest 2 card
r = cashier.post(f'/staff/guests/{g1}/checkout', {})
coid = r.url.rstrip('/').split('/')[-1]
r = cashier.post(f'/staff/checkouts/{coid}/cash', {'tendered': '1000'})
check('/staff/receipts/' in r.url, f'cash checkout paid ({flash(r)})')
print('   ', flash(r)[0])
pay = ids(r'/staff/payments/([0-9a-f-]{36})/refunds', cashier.get(r.url.replace(BASE, '')).text)
r2 = cashier.post(f'/staff/guests/{g2}/checkout', {})
co2 = r2.url.rstrip('/').split('/')[-1]
r2 = cashier.post(f'/staff/checkouts/{co2}/card', {'reference': 'AUTH1234', 'confirmed': '1'})
check('/staff/receipts/' in r2.url, f'card checkout paid ({flash(r2)} {r2.status_code} {r2.url})')

# Refund: cashier/manager requests, owner approves, completes
r = manager.get(f'/staff/receipts/{coid}')
link = re.search(r'href="(/staff/refunds\?receipt=[^"]+)"', r.text)
check(link is not None, 'receipt links to refunds')
r = manager.get(link.group(1).replace('&amp;', '&'))
pay = ids(r'/staff/payments/([0-9a-f-]{36})/refunds', r.text)
check(len(pay) >= 1, 'refund form for receipt payment')
r = manager.post(f'/staff/payments/{pay[0]}/refunds', {'amount': '50', 'reason': 'Overcharged drink'})
check(flash(r)[0] == 'Refund requested. A manager must approve it.', f'refund requested {flash(r)}')
r = owner.get('/staff/refunds')
rid = ids(r'/staff/refunds/([0-9a-f-]{36})/decide', r.text)
check(len(rid) >= 1, 'refund waiting for decision')
r = owner.post(f'/staff/refunds/{rid[0]}/decide', {'decision': 'approve'})
check(flash(r)[0] == 'Refund approved.', f'refund approved {flash(r)}')
r = owner.get('/staff/refunds')
cid = ids(r'/staff/refunds/([0-9a-f-]{36})/complete', r.text)
if cid:
    r = owner.post(f'/staff/refunds/{cid[0]}/complete', {'reference': 'CASH-OUT'})
    check(flash(r)[0] == 'Refund completed and recorded.', f'refund completed {flash(r)}')

r = waiter.get(f'/staff/visits/{vid}')
ver = re.search(r'name="expected_version" value="(\d+)"', r.text).group(1)
r = waiter.post(f'/staff/visits/{vid}/close', {'expected_version': ver})
check(any('not yet served' in e for e in flash(r)[1]), f'close blocked while food not served {flash(r)}')
kit, _ = pair(owner, 'kitchen', 'E2E money kitchen ' + U.uuid4().hex[:6])
st, board = kit.api('GET', '/kitchen/tasks')
for t in board['data']['tickets']:
    if t['state'] in ('served', 'cancelled'): continue
    v = t['version']
    for to in ['preparing', 'ready', 'served']:
        st, res = kit.api('POST', f"/kitchen/tickets/{t['id']}/transitions", {'to': to, 'version': v})
        v = res['data']['version'] if st == 200 else v
r = waiter.get(f'/staff/visits/{vid}')
ver = re.search(r'name="expected_version" value="(\d+)"', r.text).group(1)
r = waiter.post(f'/staff/visits/{vid}/close', {'expected_version': ver})
check(r.url.endswith('/staff/tables'), f'visit closes after both paid {flash(r)} {r.status_code} {r.url}')

# Kiosk — pay at cashier
kio, r = pair(owner, 'kiosk', 'E2E kiosk ' + U.uuid4().hex[:6])
check(r.url.endswith('/kiosk'), f'kiosk lands on /kiosk ({r.url})')
st, s = kio.api('POST', '/kiosk/sessions')
check(st == 201, f'kiosk session {st}')
st, menu = kio.api('GET', '/kiosk/menu')
chai = next(m for m in menu['data']['meals'] if m['name'] == 'Kenyan chai')
st, cart = kio.api('POST', '/kiosk/cart/lines', {'mealId': chai['id'], 'quantity': 3, 'removed': [], 'extras': []})
st, o = kio.api('POST', '/kiosk/orders', {'quoteDigest': cart['data']['digest'], 'route': 'cashier', 'dining': 'takeaway', 'name': 'Eve'}, {'Idempotency-Key': str(U.uuid4())})
check(st == 201 and o['data']['state'] == 'pending_payment' and o['data']['reference'], f"kiosk order awaiting cashier {st} {o}")
ref = o['data']['reference']
r = cashier.get('/staff/cashier?ref=' + ref)
kid = ids(r'/staff/kiosk-orders/([0-9a-f-]{36})/checkout', r.text)
check(len(kid) >= 1, 'cashier finds kiosk order by reference')
r = cashier.post(f'/staff/kiosk-orders/{kid[0]}/checkout', {})
co = r.url.rstrip('/').split('/')[-1]
r = cashier.post(f'/staff/checkouts/{co}/cash', {'tendered': '1000'})
check('/staff/receipts/' in r.url, f'kiosk cash paid {flash(r)} {r.status_code} {r.url}')
st, s = kio.api('GET', '/kiosk/status')
check(s['data']['collectionNumber'] is not None, f"collection number issued {s['data']['state']} #{s['data']['collectionNumber']}")
coll, r = pair(owner, 'collection', 'E2E collection ' + U.uuid4().hex[:6])
st, c = coll.api('GET', '/collection')
check(st == 200, f'collection board api {st} {c if st != 200 else list(c["data"].keys())}')

# Kiosk — M-PESA with an insufficient-funds phone, then a good one
kio.api('POST', '/kiosk/sessions')
st, cart = kio.api('POST', '/kiosk/cart/lines', {'mealId': chai['id'], 'quantity': 1, 'removed': [], 'extras': []})
st, o = kio.api('POST', '/kiosk/orders', {'quoteDigest': cart['data']['digest'], 'route': 'mpesa', 'dining': 'eat_in', 'name': 'Finn'}, {'Idempotency-Key': str(U.uuid4())})
check(st == 201, f'kiosk mpesa order {st} {o if st != 201 else ""}')
st, s = kio.api('POST', '/kiosk/mpesa-attempts', {'phone': '0700000111'})
check(st == 201, f'mpesa attempt {st} {s if st != 201 else ""}')
for _ in range(10):
    time.sleep(2); st, s = kio.api('GET', '/kiosk/status')
    if s['data']['attempt'] and s['data']['attempt']['state'] != 'pending': break
check(s['data']['attempt']['state'] == 'failed', f"insufficient funds fails: {s['data']['attempt']}")
st, s = kio.api('POST', '/kiosk/mpesa-attempts', {'phone': '0712345678'})
for _ in range(10):
    time.sleep(2); st, s = kio.api('GET', '/kiosk/status')
    if s['data']['state'] != 'pending_payment': break
check(s['data']['state'] != 'pending_payment' and s['data']['collectionNumber'], f"kiosk paid by mpesa: {s['data']['state']} #{s['data']['collectionNumber']}")

r = cashier.get('/staff/cash')
d = re.search(r'/staff/cash/drawers/([0-9a-f-]{36})/close', r.text)
