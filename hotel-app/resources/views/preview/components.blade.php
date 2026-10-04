@extends('layout')
@section('content')
    <h1>Accessible primitives</h1>
    <p>Demo components only.</p>

    <section aria-labelledby="buttons-heading">
        <h2 id="buttons-heading">Buttons</h2>
        <button type="button">Add to order</button>
        <button type="button" aria-describedby="disabled-note" disabled>Remove item</button>
        <p id="disabled-note">Removing is unavailable while the kitchen is preparing this item.</p>
    </section>

    <section aria-labelledby="fields-heading">
        <h2 id="fields-heading">Form fields</h2>
        <div class="field">
            <label for="guest-name">Guest name</label>
            <input id="guest-name" name="guest-name" type="text" aria-describedby="guest-name-error" aria-invalid="true">
            <p id="guest-name-error" class="field-error">Enter the guest name exactly as the staff recorded it.</p>
        </div>
        <div class="field">
            <label for="table-size">Table size</label>
            <select id="table-size" name="table-size">
                <option>2 guests</option>
                <option>4 guests</option>
            </select>
        </div>
    </section>

    <section aria-labelledby="dialog-heading">
        <h2 id="dialog-heading">Dialog (demo)</h2>
        <button type="button" id="open-dialog">Open review dialog</button>
        <div id="demo-dialog" class="dialog" hidden tabindex="-1" aria-labelledby="demo-dialog-title">
            <h3 id="demo-dialog-title">Review changes</h3>
            <p>Demo dialog content. Focus stays inside; Escape closes.</p>
            <button type="button" id="close-dialog">Close</button>
        </div>
    </section>

    <section aria-labelledby="drawer-heading">
        <h2 id="drawer-heading">Drawer (demo)</h2>
        <button type="button" id="open-drawer">Open ingredient drawer</button>
        <div id="demo-drawer" class="drawer" hidden tabindex="-1" aria-labelledby="demo-drawer-title">
            <h3 id="demo-drawer-title">Ingredient details</h3>
            <p>Demo drawer content.</p>
            <button type="button" id="close-drawer">Close</button>
        </div>
    </section>

    <script type="module">
        import { openDialog, closeDialog } from '/assets/js/components/ui/dialog.js';
        import { openDrawer, closeDrawer } from '/assets/js/components/ui/drawer.js';
        const dialog = document.getElementById('demo-dialog');
        const dialogOpener = document.getElementById('open-dialog');
        dialogOpener.addEventListener('click', () => openDialog(dialog, { returnFocusTo: dialogOpener }));
        document.getElementById('close-dialog').addEventListener('click', () => closeDialog(dialog, { returnFocusTo: dialogOpener }));

        const drawer = document.getElementById('demo-drawer');
        const drawerOpener = document.getElementById('open-drawer');
        drawerOpener.addEventListener('click', () => openDrawer(drawer, { returnFocusTo: drawerOpener }));
        document.getElementById('close-drawer').addEventListener('click', () => closeDrawer(drawer, { returnFocusTo: drawerOpener }));
    </script>

    <section aria-labelledby="tabs-heading">
        <h2 id="tabs-heading">Tabs (demo)</h2>
        <div role="tablist" aria-label="Kitchen stations">
            <button role="tab" id="tab-hot" aria-selected="true" aria-controls="panel-hot">Hot</button>
            <button role="tab" id="tab-cold" aria-selected="false" aria-controls="panel-cold" tabindex="-1">Cold</button>
        </div>
        <div role="tabpanel" id="panel-hot" aria-labelledby="tab-hot" tabindex="0">Hot kitchen tickets appear here.</div>
        <div role="tabpanel" id="panel-cold" aria-labelledby="tab-cold" tabindex="0" hidden>Cold kitchen tickets appear here.</div>
        <script type="module">
            import { initTabs } from '/assets/js/components/ui/tabs.js';
            initTabs(document.querySelector('[aria-label="Kitchen stations"]'));
        </script>
    </section>

    <section aria-labelledby="table-heading">
        <h2 id="table-heading">Table and badge (demo)</h2>
        <table>
            <caption>Guests at table 4</caption>
            <thead><tr><th scope="col">Guest</th><th scope="col">Status</th></tr></thead>
            <tbody>
                <tr><th scope="row">Guest 1</th><td><span class="badge">Seated</span></td></tr>
                <tr><th scope="row">Guest 2</th><td><span class="badge">Ordering</span></td></tr>
            </tbody>
        </table>
    </section>

    <section aria-labelledby="status-heading">
        <h2 id="status-heading">Status message (demo)</h2>
        <p role="status" id="demo-status">Order sent to the kitchen.</p>
    </section>

    <section aria-labelledby="states-heading">
        <h2 id="states-heading">System states (demo)</h2>
        <section class="state" aria-labelledby="loading-title"><h3 id="loading-title">Loading</h3><p>Loading the menu…</p><p><a href="/preview/components">Try again</a></p></section>
        <section class="state" aria-labelledby="empty-title"><h3 id="empty-title">Empty</h3><p>No meals are published yet.</p><p><a href="/preview/components">Refresh the menu</a></p></section>
        <section class="state" aria-labelledby="denied-title"><h3 id="denied-title">Denied</h3><p>You cannot view this bill.</p><p><a href="/preview/components">Back to the menu</a></p></section>
        <section class="state" aria-labelledby="error-title"><h3 id="error-title">Recoverable error</h3><p>The kitchen could not be reached.</p><p><a href="/preview/components">Try again</a></p></section>
    </section>

    <section aria-labelledby="banner-heading">
        <h2 id="banner-heading">Connection banner (demo)</h2>
        <p id="connection-banner" role="status" aria-live="polite" data-state=""></p>
        <script type="module">
            import { initConnectionBanner } from '/assets/js/lib/connection-banner.js';
            const banner = document.getElementById('connection-banner');
            const probe = async () => {
                const response = await fetch('/locales/en.json', { cache: 'no-store' });
                if (!response.ok) {
                    const error = new Error('provider');
                    error.kind = response.status >= 500 ? 'provider' : 'unreachable';
                    throw error;
                }
            };
            fetch('/locales/en.json')
                .then((r) => (r.ok ? r.json() : Promise.reject(new Error('locale'))))
                .catch(() => ({}))
                .then((keys) => {
                    initConnectionBanner({
                        banner,
                        fetchProbe: probe,
                        messages: { offline: keys['connection.offline'], unreachable: keys['connection.unreachable'], provider: keys['connection.provider'] },
                    });
                });
        </script>
    </section>
@endsection
