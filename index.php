<?php
// ─── PHP Backend ────────────────────────────────────────────────────────────

$config_dir = 'device_configs/';
$configs    = glob($config_dir . '*.json') ?: [];

// Load UI config (category icons, etc.)
$ui_config = [];
if (file_exists('ui_config.json')) {
    $ui_config = json_decode(file_get_contents('ui_config.json'), true) ?? [];
}

// Load full device data for JS
$all_devices = [];
foreach ($configs as $file) {
    $data = json_decode(file_get_contents($file), true);
    if ($data) {
        $all_devices[] = [
            'file'         => $file,
            'type'         => $data['type'] ?? 'wifi',
            'category'     => $data['device_info']['category'] ?? 'Other',
            'model'        => $data['device_info']['model'] ?? basename($file, '.json'),
            'manufacturer' => $data['device_info']['manufacturer'] ?? '',
            'image_url'    => $data['device_info']['image_url'] ?? null,
        ];
    }
}

$result      = null;
$result_ok   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_file = $_POST['model'] ?? '';

    if ($selected_file && file_exists($selected_file)) {
        $device_config = json_decode(file_get_contents($selected_file), true);
        $type          = $device_config['type'] ?? 'wifi';

        $user_input = [
            'device_id'     => $_POST['id']   ?? '',
            'friendly_name' => $_POST['name'] ?? '',
            'mqtt_server'   => 'piaasem.local:1883',
            'mqtt_user'     => 'siebe',
            'mqtt_pass'     => '2250',
        ];

        if ($type === 'wifi') {
            $user_input['device_ip'] = $_POST['ip'] ?? '';
            $url = 'http://nodered:1880/add-device';
        } else {
            $url = 'http://nodered:1880/add-zigbee-device';
        }

        $payload = ['user_input' => $user_input, 'device_config' => $device_config];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POSTFIELDS,   json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER,   ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $raw    = curl_exec($ch);
        $result_ok = ($raw !== false);
        $result = $raw ?: curl_error($ch);
        curl_close($ch);
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f3f4f6; font-family: 'Segoe UI', sans-serif; }

        .category-btn { transition: all .15s ease; }
        .category-btn.active { background: #2563eb; color: #fff; box-shadow: 0 2px 8px rgba(37,99,235,.35); }
        .category-btn:not(.active):hover { background: #e5e7eb; }

        .device-card { transition: all .15s ease; cursor: pointer; border: 2px solid transparent; }
        .device-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
        .device-card.selected { border-color: #2563eb; background: #eff6ff; }

        .form-panel { animation: fadeIn .2s ease; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }

        input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
    </style>
</head>
<body class="min-h-screen p-6">

<div class="max-w-6xl mx-auto">

    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">
            <i class="fas fa-home mr-2 text-blue-600"></i>Device Manager
        </h1>
        <p class="text-gray-500 mt-1">Add a new device to Home Assistant</p>
    </div>

    <!-- Result banner -->
    <?php if ($result !== null): ?>
    <div class="mb-6 p-4 rounded-xl flex items-center gap-3 <?= $result_ok ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
        <i class="fas <?= $result_ok ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> text-xl"></i>
        <div>
            <strong><?= $result_ok ? 'Device added!' : 'Error' ?></strong>
            <span class="ml-2 text-sm opacity-75"><?= htmlspecialchars($result) ?></span>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-12 gap-6">

        <!-- ── Column 1: Categories ─────────────────────────────────────── -->
        <div class="col-span-12 md:col-span-3">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Categories</p>
            <div id="category-list" class="space-y-2">
                <!-- Populated by JS -->
            </div>
        </div>

        <!-- ── Column 2: Devices ────────────────────────────────────────── -->
        <div class="col-span-12 md:col-span-4">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Devices</p>
            <!-- Search bar -->
            <div class="relative mb-3">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                <input type="text" id="device-search" placeholder="Search devices…"
                       class="w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition">
            </div>
            <div id="device-list" class="space-y-3">
                <div class="text-gray-400 text-sm italic">Select a category</div>
            </div>
        </div>

        <!-- ── Column 3: Form ───────────────────────────────────────────── -->
        <div class="col-span-12 md:col-span-5">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Configuration</p>

            <div id="form-placeholder" class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-2xl p-12 text-center text-gray-400">
                <i class="fas fa-mouse-pointer text-3xl mb-3 block"></i>
                <p>Select a device to configure</p>
            </div>

            <form id="device-form" method="post" class="form-panel hidden bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-5">

                <!-- Hidden: selected file path -->
                <input type="hidden" name="model" id="input-model">

                <!-- Selected device summary -->
                <div id="device-summary" class="flex items-center gap-3 pb-4 border-b border-gray-100">
                    <div id="summary-icon" class="w-10 h-10 rounded-xl flex items-center justify-center text-white text-lg bg-blue-500">
                        <i class="fas fa-plug"></i>
                    </div>
                    <div>
                        <p id="summary-model" class="font-bold text-gray-800"></p>
                        <p id="summary-manufacturer" class="text-sm text-gray-500"></p>
                    </div>
                    <span id="summary-badge" class="ml-auto px-2 py-1 rounded-lg text-xs font-bold uppercase"></span>
                </div>

                <!-- Friendly name -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        <i class="fas fa-tag mr-1 text-gray-400"></i>Friendly Name
                    </label>
                    <input type="text" name="name" required placeholder="e.g. Kitchen Plug"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm transition">
                </div>

                <!-- Device ID -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        <i class="fas fa-fingerprint mr-1 text-gray-400"></i>Device ID
                    </label>
                    <input type="text" name="id" id="input-id" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm transition">
                    <p id="id-hint" class="text-xs text-gray-400 mt-1"></p>
                </div>

                <!-- IP Address (WiFi only) -->
                <div id="ip-field">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        <i class="fas fa-network-wired mr-1 text-gray-400"></i>IP Address
                    </label>
                    <input type="text" name="ip" id="input-ip" value="10.3.141.1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm transition">
                </div>

                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold py-3 rounded-xl transition-colors shadow-md flex items-center justify-center gap-2">
                    <i class="fas fa-plus-circle"></i>
                    Add to Home Assistant
                </button>
            </form>
        </div>

    </div><!-- /grid -->
</div><!-- /container -->

<script>
// ── Data from PHP ────────────────────────────────────────────────────────────
const devices  = <?= json_encode($all_devices, JSON_UNESCAPED_UNICODE) ?>;
const uiConfig = <?= json_encode($ui_config,   JSON_UNESCAPED_UNICODE) ?>;

// ── Config from ui_config.json ───────────────────────────────────────────────
const categoryIcons = uiConfig.categoryIcons ?? {};
const deviceTypes   = uiConfig.deviceTypes   ?? {};
const ALL_LABEL = 'All Devices';

// ── Helper: get type config with fallback ────────────────────────────────────
function getTypeConfig(type) {
    return deviceTypes[type] ?? { icon: 'fa-microchip', color: 'gray', label: type };
}

// Tailwind color classes derived from a color name
function typeClasses(color, variant) {
    // variant: 'bg-light' | 'text-light' | 'bg-solid' | 'badge-bg' | 'badge-text'
    const map = {
        'bg-light':   `bg-${color}-50`,
        'text-light': `text-${color}-400`,
        'bg-solid':   `bg-${color}-500`,
        'badge-bg':   `bg-${color}-100`,
        'badge-text': `text-${color}-700`,
    };
    return map[variant] ?? '';
}

// ── State ────────────────────────────────────────────────────────────────────
let activeCategory = ALL_LABEL;
let activeDevice   = null;
let searchQuery    = '';

// ── DOM refs ─────────────────────────────────────────────────────────────────
const catList     = document.getElementById('category-list');
const devList     = document.getElementById('device-list');
const form        = document.getElementById('device-form');
const placeholder = document.getElementById('form-placeholder');
const ipField     = document.getElementById('ip-field');
const ipInput     = document.getElementById('input-ip');
const searchInput = document.getElementById('device-search');

// ── Build category list ──────────────────────────────────────────────────────
const categories = [ALL_LABEL, ...new Set(devices.map(d => d.category))];

function buildCategoryList() {
    catList.innerHTML = '';
    categories.forEach(cat => {
        const isAll  = cat === ALL_LABEL;
        const icon   = isAll ? 'fa-layer-group' : (categoryIcons[cat] || 'fa-microchip');
        const btn    = document.createElement('button');
        btn.type         = 'button';
        btn.dataset.cat  = cat;
        btn.className    = 'category-btn w-full text-left px-4 py-3 rounded-xl bg-white flex items-center gap-3 text-gray-700 font-medium';
        btn.innerHTML    = `<i class="fas ${icon} w-5 text-center text-blue-400"></i><span>${cat}</span><i class="fas fa-chevron-right ml-auto text-xs opacity-40"></i>`;
        btn.addEventListener('click', () => { searchInput.value = ''; searchQuery = ''; selectCategory(cat); });
        catList.appendChild(btn);
    });
}

buildCategoryList();
selectCategory(ALL_LABEL);

// ── Search bar ───────────────────────────────────────────────────────────────
searchInput.addEventListener('input', () => {
    searchQuery = searchInput.value.trim().toLowerCase();
    renderDevices();
});

// ── Select category ──────────────────────────────────────────────────────────
function selectCategory(cat) {
    activeCategory = cat;
    activeDevice   = null;

    catList.querySelectorAll('.category-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.cat === cat);
    });

    form.classList.add('hidden');
    placeholder.classList.remove('hidden');
    renderDevices();
}

// ── Render device cards (respects category + search) ─────────────────────────
function renderDevices() {
    let pool = activeCategory === ALL_LABEL
        ? devices
        : devices.filter(d => d.category === activeCategory);

    if (searchQuery) {
        pool = pool.filter(d =>
            d.model.toLowerCase().includes(searchQuery) ||
            d.manufacturer.toLowerCase().includes(searchQuery) ||
            d.category.toLowerCase().includes(searchQuery)
        );
    }

    devList.innerHTML = '';

    if (!pool.length) {
        devList.innerHTML = '<div class="text-gray-400 text-sm italic">No devices found</div>';
        return;
    }

    pool.forEach(dev => {
        const tc   = getTypeConfig(dev.type);
        const card = document.createElement('div');
        card.className    = 'device-card bg-white rounded-xl p-4 flex items-center';
        card.dataset.file = dev.file;

        const thumbHtml = dev.image_url
            ? `<img src="${dev.image_url}" alt="${dev.model}" class="w-12 h-12 object-contain rounded-lg bg-gray-50 border border-gray-100 p-1 flex-shrink-0" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'w-12 h-12 rounded-lg flex items-center justify-center bg-gray-100 text-gray-400 flex-shrink-0',innerHTML:'<i class=\\'fas fa-microchip\\'></i>'}));">`
            : `<div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0 ${typeClasses(tc.color,'bg-light')} ${typeClasses(tc.color,'text-light')}"><i class="fas ${tc.icon}"></i></div>`;

        card.innerHTML = `
            ${thumbHtml}
            <div class="flex-1 min-w-0 ml-3">
                <p class="font-bold text-gray-800 truncate">${dev.model}</p>
                <p class="text-sm text-gray-500 truncate">${dev.manufacturer}</p>
            </div>
            <span class="ml-3 flex-shrink-0 px-2 py-1 rounded-lg text-xs font-bold uppercase ${typeClasses(tc.color,'badge-bg')} ${typeClasses(tc.color,'badge-text')}">
                <i class="fas ${tc.icon} mr-1"></i>${tc.label ?? dev.type}
            </span>`;
        card.addEventListener('click', () => selectDevice(dev, card));
        devList.appendChild(card);
    });
}

// ── Select device ────────────────────────────────────────────────────────────
function selectDevice(dev, cardEl) {
    activeDevice = dev;

    devList.querySelectorAll('.device-card').forEach(c => c.classList.remove('selected'));
    cardEl.classList.add('selected');

    document.getElementById('input-model').value = dev.file;

    const tc = getTypeConfig(dev.type);
    document.getElementById('summary-model').textContent        = dev.model;
    document.getElementById('summary-manufacturer').textContent = dev.manufacturer;

    const badge = document.getElementById('summary-badge');
    badge.textContent = tc.label ?? dev.type;
    badge.className   = `ml-auto px-2 py-1 rounded-lg text-xs font-bold uppercase ${typeClasses(tc.color,'badge-bg')} ${typeClasses(tc.color,'badge-text')}`;

    const summaryIcon = document.getElementById('summary-icon');
    if (dev.image_url) {
        summaryIcon.className = 'w-12 h-12 rounded-xl overflow-hidden border border-gray-200 bg-gray-50 flex-shrink-0';
        summaryIcon.innerHTML = `<img src="${dev.image_url}" alt="${dev.model}" class="w-full h-full object-contain p-1" onerror="this.parentElement.className='w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg ${typeClasses(tc.color,'bg-solid')}';this.parentElement.innerHTML='<i class=\\'fas ${tc.icon}\\'></i>';">`;
    } else {
        summaryIcon.className = `w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg ${typeClasses(tc.color,'bg-solid')}`;
        summaryIcon.innerHTML = `<i class="fas ${tc.icon}"></i>`;
    }

    const showIp = tc.showIpField ?? (dev.type === 'wifi');
    ipField.style.display = showIp ? 'block' : 'none';
    ipInput.required      = showIp;
    document.getElementById('id-hint').textContent = tc.idHint ?? '';

    placeholder.classList.add('hidden');
    form.classList.remove('hidden');
}
</script>
</body>
</html>
