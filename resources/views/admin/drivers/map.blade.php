@extends('layouts.dashboard')

@section('title', app()->getLocale() === 'ar' ? 'خريطة التوصيل - الإمارات' : 'UAE Delivery Map')
@section('page-title', app()->getLocale() === 'ar' ? 'خريطة التوصيل - الإمارات' : 'UAE Delivery Map')

@section('content')
    @php($isAr = app()->getLocale() === 'ar')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous">
    <style>
        .dh-uae-shell {
            max-width: 1380px;
            margin: 0 auto;
            color: #e2e8f0
        }

        .dh-uae-panel {
            background: #0d1b2e;
            border: 1px solid #22354c;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, .13)
        }

        .dh-uae-card {
            background: #10243a;
            border: 1px solid #27405a;
            border-radius: 16px;
            padding: 17px 20px
        }

        .dh-uae-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 15px;
            border-radius: 11px;
            border: 1px solid #34506a;
            background: #142b43;
            color: #e2e8f0;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none
        }

        .dh-uae-button:hover {
            background: #1d3c58
        }

        .dh-uae-button.primary {
            background: #0891b2;
            color: #fff;
            border-color: #0891b2
        }

        .dh-uae-button.primary:hover {
            background: #0e7490
        }

        #drivers-map {
            display: block;
            width: 100%;
            height: 510px;
            min-height: 510px;
            position: relative;
            isolation: isolate;
            z-index: 0;
            direction: ltr;
            background: #e5e7eb;
            border-radius: 14px;
            overflow: hidden
        }

        /* Prevent global dashboard/Tailwind image rules from breaking Leaflet tiles. */
        #drivers-map .leaflet-tile {
            max-width: none !important;
            max-height: none !important;
            width: 256px;
            height: 256px;
            padding: 0;
            border: 0;
            box-shadow: none
        }

        #drivers-map .leaflet-control-container {
            direction: ltr
        }

        #drivers-map .leaflet-control-attribution {
            font-size: 10px
        }

        #drivers-map .leaflet-popup-content-wrapper {
            border-radius: 12px
        }

        .dh-uae-pin {
            width: 19px;
            height: 19px;
            border: 3px solid white;
            border-radius: 50%;
            box-shadow: 0 2px 12px #0006
        }

        .dh-uae-pin.recent {
            background: #10b981
        }

        .dh-uae-pin.old {
            background: #f59e0b
        }

        .dh-uae-status {
            font-size: 12px;
            color: #94a3b8;
            min-height: 20px
        }

        .dh-uae-legend {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
            color: #94a3b8;
            font-size: 12px
        }

        .dh-uae-legend i {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-inline-end: 6px
        }

        @media(max-width:640px) {
            #drivers-map {
                height: 390px;
                min-height: 390px
            }

            .dh-uae-card {
                padding: 14px
            }
        }
    </style>

    <div class="dh-uae-shell space-y-5" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="mb-2 text-xs font-bold tracking-widest text-cyan-400">RASLO · UAE OPERATIONS</div>
                <h1 class="text-2xl font-black text-white sm:text-3xl">
                    {{ $isAr ? 'مركز عمليات التوصيل في الإمارات' : 'UAE Delivery Operations Center' }}</h1>
                <p class="mt-2 text-sm text-slate-400">
                    {{ $isAr ? 'متابعة آخر مواقع السائقين المسجلة في دولة الإمارات' : 'Monitor the latest recorded driver locations in the UAE' }}
                </p>
            </div>
            <a href="{{ route('admin.drivers.index') }}"
                class="dh-uae-button">{{ $isAr ? 'العودة إلى السائقين' : 'Back to drivers' }}</a>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="dh-uae-card">
                <div class="text-xs text-slate-400">{{ $isAr ? 'السائقون داخل الإمارات' : 'Drivers in UAE' }}</div>
                <div id="stat-total" class="mt-2 text-3xl font-black text-white">—</div>
            </div>
            <div class="dh-uae-card">
                <div class="text-xs text-slate-400">{{ $isAr ? 'مواقع حديثة (5 دقائق)' : 'Recent (5 min)' }}</div>
                <div id="stat-fresh" class="mt-2 text-3xl font-black text-emerald-400">—</div>
            </div>
            <div class="dh-uae-card">
                <div class="text-xs text-slate-400">{{ $isAr ? 'مواقع أقدم' : 'Older locations' }}</div>
                <div id="stat-stale" class="mt-2 text-3xl font-black text-amber-400">—</div>
            </div>
        </div>

        <div class="dh-uae-panel p-3 sm:p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-white">{{ $isAr ? 'خريطة الإمارات' : 'United Arab Emirates Map' }}</h2>
                    <p id="map-status" role="status" aria-live="polite" class="dh-uae-status mt-1">
                        {{ $isAr ? 'جاري تهيئة الخريطة…' : 'Initializing map…' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button id="map-uae" type="button"
                        class="dh-uae-button">{{ $isAr ? 'عرض الإمارات' : 'View UAE' }}</button>
                    <button id="map-fit" type="button"
                        class="dh-uae-button">{{ $isAr ? 'مواقع السائقين' : 'Driver locations' }}</button>
                    <button id="map-refresh" type="button"
                        class="dh-uae-button primary">{{ $isAr ? 'تحديث' : 'Refresh' }}</button>
                </div>
            </div>
            <div id="drivers-map" role="region"
                aria-label="{{ $isAr ? 'خريطة الإمارات ومواقع السائقين' : 'UAE map and driver locations' }}"></div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div class="dh-uae-legend">
                    <span><i style="background:#10b981"></i>{{ $isAr ? 'موقع حديث' : 'Recent location' }}</span>
                    <span><i style="background:#f59e0b"></i>{{ $isAr ? 'موقع قديم' : 'Older location' }}</span>
                </div>
                <p class="text-xs text-slate-400">
                    {{ $isAr ? 'آخر 24 ساعة · تحديث كل 30 ثانية · ليست مواقع مباشرة مضمونة' : 'Last 24 hours · Refresh every 30 seconds · Not guaranteed live' }}
                </p>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous"></script>
    <script>
        (function() {
            const isAr = @json($isAr);
            const endpoint = @json(route('admin.drivers.locations'));
            const tr = (ar, en) => isAr ? ar : en;
            const status = document.getElementById('map-status');
            const refreshBtn = document.getElementById('map-refresh');
            const fitBtn = document.getElementById('map-fit');
            const uaeBtn = document.getElementById('map-uae');
            if (!window.L) {
                status.textContent = tr('تعذر تحميل مكتبة Leaflet. تحقق من الإنترنت.',
                    'Leaflet could not load. Check your internet connection.');
                return;
            }

            // UAE geographical envelope; it is used for viewport and approximate filtering.
            const bounds = L.latLngBounds([
                [22.55, 51.45],
                [26.15, 56.65]
            ]);
            const map = L.map('drivers-map', {
                zoomControl: true,
                scrollWheelZoom: false,
                attributionControl: true
            });
            const tileLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                minZoom: 4,
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors'
            }).addTo(map);
            let tileErrors = 0;
            tileLayer.on('tileerror', function() {
                tileErrors++;
                if (tileErrors >= 3) status.textContent = tr(
                    'تعذر تحميل صور الخريطة؛ تحقق من الإنترنت أو إعدادات المتصفح.',
                    'Map tiles failed to load; check network or browser restrictions.');
            });
            tileLayer.on('tileload', function() {
                tileErrors = 0;
            });

            const markers = L.layerGroup().addTo(map);
            let points = [];
            let busy = false;
            let activeController = null;
            const showUae = () => {
                map.invalidateSize({
                    pan: false
                });
                map.fitBounds(bounds, {
                    padding: [16, 16],
                    maxZoom: 8,
                    animate: false
                });
            };
            const fitDrivers = () => {
                if (!points.length) {
                    showUae();
                    return;
                }
                map.invalidateSize({
                    pan: false
                });
                if (points.length === 1) map.setView(points[0], 12);
                else map.fitBounds(L.latLngBounds(points), {
                    padding: [35, 35],
                    maxZoom: 13
                });
            };
            uaeBtn.addEventListener('click', showUae);
            fitBtn.addEventListener('click', fitDrivers);
            showUae();
            requestAnimationFrame(() => requestAnimationFrame(showUae));
            window.addEventListener('load', () => map.invalidateSize({
                pan: false
            }));
            window.addEventListener('resize', () => map.invalidateSize({
                pan: false
            }));
            if (window.ResizeObserver) {
                new ResizeObserver(() => map.invalidateSize({
                    pan: false
                })).observe(document.getElementById('drivers-map'));
            }

            const setCount = (id, value) => {
                document.getElementById(id).textContent = String(value);
            };
            const isUae = (lat, lng) => bounds.contains([lat, lng]);
            async function refresh() {
                if (busy || document.hidden) return;
                busy = true;
                refreshBtn.disabled = true;
                const controller = new AbortController();
                activeController = controller;
                try {
                    const response = await fetch(endpoint, {
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json'
                        },
                        cache: 'no-store',
                        signal: controller.signal
                    });
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    const payload = await response.json();
                    const drivers = Array.isArray(payload.data) ? payload.data : [];
                    const next = [];
                    let recent = 0;
                    for (const driver of drivers) {
                        if (driver.latitude == null || driver.longitude == null) continue;
                        const lat = Number(driver.latitude),
                            lng = Number(driver.longitude);
                        if (!Number.isFinite(lat) || !Number.isFinite(lng) || !isUae(lat, lng)) continue;
                        const stamp = Date.parse(driver.last_location_at || '');
                        const age = Date.now() - stamp;
                        const fresh = Number.isFinite(age) && age >= 0 && age <= 300000;
                        if (fresh) recent++;
                        next.push({
                            lat,
                            lng,
                            fresh,
                            name: String(driver.name || tr('سائق', 'Driver')),
                            time: Number.isFinite(stamp) ? new Date(stamp).toLocaleString(isAr ? 'ar-AE' :
                                'en-GB') : '—'
                        });
                    }
                    markers.clearLayers();
                    points = [];
                    for (const driver of next) {
                        const icon = L.divIcon({
                            className: '',
                            html: '<div class="dh-uae-pin ' + (driver.fresh ? 'recent' : 'old') +
                                '"></div>',
                            iconSize: [19, 19],
                            iconAnchor: [9, 9]
                        });
                        const marker = L.marker([driver.lat, driver.lng], {
                            icon
                        }).addTo(markers);
                        const popup = document.createElement('div');
                        const name = document.createElement('strong');
                        name.textContent = driver.name;
                        const info = document.createElement('p');
                        info.textContent = tr('آخر تحديث: ', 'Last update: ') + driver.time;
                        popup.append(name, info);
                        marker.bindPopup(popup);
                        points.push([driver.lat, driver.lng]);
                    }
                    setCount('stat-total', points.length);
                    setCount('stat-fresh', recent);
                    setCount('stat-stale', points.length - recent);
                    status.textContent = tr('تم تحديث البيانات: ', 'Data updated: ') + new Date()
                        .toLocaleTimeString(isAr ? 'ar-AE' : 'en-GB') + (points.length ? '' : tr(
                            ' — لا توجد مواقع مسجلة داخل الإمارات', ' — No recorded UAE driver locations'));
                } catch (error) {
                    if (error.name !== 'AbortError') status.textContent = tr('تعذر تحميل بيانات السائقين: ',
                        'Could not load driver data: ') + error.message;
                } finally {
                    busy = false;
                    refreshBtn.disabled = false;
                    activeController = null;
                }
            }
            refreshBtn.addEventListener('click', refresh);
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) activeController?.abort();
                else {
                    map.invalidateSize({
                        pan: false
                    });
                    refresh();
                }
            });
            setInterval(refresh, 30000);
            refresh();
        })();
    </script>
@endsection
