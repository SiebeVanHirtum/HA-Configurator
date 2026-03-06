<?php

$config_dir = 'device_configs/';
$configs = glob($config_dir . '*.json') ?: [];

// Pre-load type for each config so JS can use it
$config_types = [];
foreach ($configs as $file) {
    $data = json_decode(file_get_contents($file), true);
    $config_types[$file] = $data['type'] ?? 'wifi';
}

$result = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $selected_file = $_POST['model'];

    if (file_exists($selected_file)) {
        $device_config = json_decode(file_get_contents($selected_file), true);
        $type = $device_config['type'] ?? 'wifi';

        $user_input = [
            "device_id"     => $_POST['id'],
            "friendly_name" => $_POST['name'],
            "mqtt_server"   => "piaasem.local:1883",
            "mqtt_user"     => "siebe",
            "mqtt_pass"     => "2250"
        ];

        if ($type === 'wifi') {
            $user_input['device_ip'] = $_POST['ip'] ?? '';
            $url = "http://nodered:1880/add-device";
        } else {
            $url = "http://nodered:1880/add-zigbee-device";
        }

        $data = ["user_input" => $user_input, "device_config" => $device_config];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <title>Device Manager</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 40px auto; }
        label { display: block; margin-top: 12px; font-weight: bold; }
        input, select { width: 100%; padding: 6px; margin-top: 4px; box-sizing: border-box; }
        button { margin-top: 16px; padding: 10px 20px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .result { color: green; margin-top: 16px; }
        #ip-field { display: none; }
        .badge { font-size: 12px; padding: 2px 8px; border-radius: 10px; margin-left: 8px; vertical-align: middle; }
        .badge-wifi { background: #e3f2fd; color: #1565c0; }
        .badge-zigbee { background: #e8f5e9; color: #2e7d32; }
    </style>
</head>
<body>
<h2>Device Manager</h2>

<?php if ($result): ?>
    <div class="result"><strong>Resultaat:</strong> <?= htmlspecialchars($result) ?></div>
<?php endif; ?>

<form method="post" id="device-form">
    <label>Model:
        <select name="model" id="model-select" required onchange="onModelChange(this)">
            <?php foreach ($configs as $file):
                $type = $config_types[$file];
                $label = str_replace([$config_dir, '.json'], '', $file);
                $icon = $type === 'zigbee' ? '🔵' : '📶';
            ?>
                <option value="<?= $file ?>" data-type="<?= $type ?>">
                    <?= $icon . ' ' . $label ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <div id="ip-field">
        <label>IP Adres: <input type="text" name="ip" id="ip-input" value="10.3.141.1"></label>
    </div>

    <label>Device ID:
        <input type="text" name="id" required>
        <small id="id-hint" style="color:#888;"></small>
    </label>

    <label>Naam: <input type="text" name="name" required></label>

    <button type="submit">Voeg toe aan Home Assistant</button>
</form>

<script>
const configTypes = <?= json_encode($config_types) ?>;

function onModelChange(select) {
    const type = select.options[select.selectedIndex].dataset.type;
    const ipField = document.getElementById('ip-field');
    const ipInput = document.getElementById('ip-input');
    const idHint  = document.getElementById('id-hint');

    if (type === 'wifi') {
        ipField.style.display = 'block';
        ipInput.required = true;
        idHint.textContent = 'bv. shellyplug-s-01';
    } else {
        ipField.style.display = 'none';
        ipInput.required = false;
        idHint.textContent = 'Moet overeenkomen met de Zigbee2MQTT naam';
    }
}

// Trigger on page load
onModelChange(document.getElementById('model-select'));
</script>
</body>
</html>
