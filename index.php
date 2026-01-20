<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión
session_start();

// Definir archivo para persistencia
define('API_KEY_FILE', '/tmp/peakerr_api_key.txt');
define('ORDERS_FILE', '/tmp/peakerr_orders.json');

class PeakerrAPI
{
    private $api_url = 'http://peakerr.com/api/v2';
    private $api_key;

    public function __construct($api_key)
    {
        $this->api_key = $api_key;
    }

    public function services()
    {
        return json_decode($this->connect([
            'key' => $this->api_key,
            'action' => 'services',
        ]), true);
    }

    public function balance()
    {
        return json_decode($this->connect([
            'key' => $this->api_key,
            'action' => 'balance',
        ]), true);
    }

    public function order($data)
    {
        $post = array_merge(['key' => $this->api_key, 'action' => 'add'], $data);
        return json_decode($this->connect($post), true);
    }

    private function connect($post)
    {
        $_post = [];
        if (is_array($post)) {
            foreach ($post as $name => $value) {
                $_post[] = $name . '=' . urlencode($value);
            }
        }

        $ch = curl_init($this->api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if (is_array($post)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, join('&', $_post));
        }
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $result = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log('CURL Error: ' . curl_error($ch));
            $result = json_encode(['error' => curl_error($ch)]);
        }
        
        curl_close($ch);
        return $result;
    }
}

// Cargar API key desde archivo si existe
if (file_exists(API_KEY_FILE)) {
    $_SESSION['api_key'] = file_get_contents(API_KEY_FILE);
}

// Manejar peticiones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['ajax'];
    
    if ($action === 'save_api_key') {
        $api_key = trim($_POST['api_key']);
        $_SESSION['api_key'] = $api_key;
        file_put_contents(API_KEY_FILE, $api_key);
        echo json_encode(['success' => true]);
        exit;
    }
    
    if (!isset($_SESSION['api_key']) || empty($_SESSION['api_key'])) {
        echo json_encode(['error' => 'API Key no configurada']);
        exit;
    }
    
    $api = new PeakerrAPI($_SESSION['api_key']);
    
    switch ($action) {
        case 'get_services':
            $services = $api->services();
            if (isset($services['error'])) {
                echo json_encode(['error' => $services['error']]);
            } else {
                $tiktok_services = array_filter($services, function($s) {
                    $name = strtolower($s['name'] ?? '');
                    $category = strtolower($s['category'] ?? '');
                    return strpos($name, 'tiktok') !== false || strpos($category, 'tiktok') !== false;
                });
                echo json_encode(array_values($tiktok_services));
            }
            break;
            
        case 'get_balance':
            $balance = $api->balance();
            echo json_encode($balance);
            break;
            
        case 'create_order':
            $result = $api->order([
                'service' => $_POST['service'],
                'link' => $_POST['link'],
                'quantity' => $_POST['quantity']
            ]);
            
            if (isset($result['order'])) {
                // Cargar órdenes existentes
                $orders = [];
                if (file_exists(ORDERS_FILE)) {
                    $orders = json_decode(file_get_contents(ORDERS_FILE), true) ?: [];
                }
                
                // Agregar nueva orden
                array_unshift($orders, [
                    'id' => $result['order'],
                    'service' => $_POST['service_name'],
                    'link' => $_POST['link'],
                    'quantity' => $_POST['quantity'],
                    'cost' => $_POST['cost'],
                    'date' => date('Y-m-d H:i:s')
                ]);
                
                // Mantener solo 50
                $orders = array_slice($orders, 0, 50);
                
                // Guardar
                file_put_contents(ORDERS_FILE, json_encode($orders));
            }
            
            echo json_encode($result);
            break;
            
        case 'get_order_history':
            $orders = [];
            if (file_exists(ORDERS_FILE)) {
                $orders = json_decode(file_get_contents(ORDERS_FILE), true) ?: [];
            }
            echo json_encode($orders);
            break;
            
        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
    exit;
}

$hasApiKey = isset($_SESSION['api_key']) && !empty($_SESSION['api_key']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TikBoost Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
        .animate-spin { animation: spin 1s linear infinite; }
        .gradient-text {
            background: linear-gradient(to right, #a78bfa, #f472b6, #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-purple-900 to-gray-900 text-white min-h-screen">

    <!-- Pantalla de configuración -->
    <div id="settingsScreen" class="<?php echo $hasApiKey ? 'hidden' : ''; ?> min-h-screen flex items-center justify-center p-4">
        <div class="bg-white/10 backdrop-blur-xl rounded-2xl border border-white/20 p-8 max-w-md w-full">
            <h2 class="text-2xl font-bold mb-4">Configuración API</h2>
            <p class="text-gray-300 mb-4">Ingresa tu API Key de Peakerr</p>
            <input type="password" id="apiKeyInput" placeholder="API Key" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white mb-4">
            <button onclick="saveApiKey()" class="w-full bg-gradient-to-r from-purple-500 to-pink-500 py-3 rounded-xl font-semibold">
                Guardar y Continuar
            </button>
        </div>
    </div>

    <!-- Pantalla principal -->
    <div id="mainScreen" class="<?php echo !$hasApiKey ? 'hidden' : ''; ?>">
        <header class="border-b border-white/10 bg-black/20 p-4">
            <div class="max-w-6xl mx-auto flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl"></div>
                    <span class="text-xl font-bold">TikBoost Pro</span>
                </div>
                <div class="flex gap-4">
                    <div id="balanceDisplay" class="bg-green-500/20 border border-green-500/30 rounded-lg px-3 py-1.5">
                        <span class="text-sm font-medium">$0.00</span>
                    </div>
                    <button onclick="showSettings()" class="p-2 hover:bg-white/10 rounded-lg">⚙️</button>
                </div>
            </div>
        </header>

        <div class="max-w-6xl mx-auto px-4 py-16">
            <div class="text-center mb-12">
                <h1 class="text-5xl font-bold mb-4 gradient-text">Impulsa tu TikTok</h1>
                <p class="text-xl text-gray-300"><span id="servicesCount">0</span> servicios disponibles</p>
            </div>

            <div class="grid lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2">
                    <div class="bg-white/5 backdrop-blur-xl rounded-2xl border border-white/10 p-8">
                        <div id="successView" class="hidden text-center py-12">
                            <div class="w-20 h-20 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-4">✓</div>
                            <h3 class="text-2xl font-bold mb-2">¡Pedido creado!</h3>
                            <p class="text-gray-300 mb-4">ID: #<span id="orderIdDisplay"></span></p>
                        </div>

                        <div id="orderForm">
                            <div class="mb-6">
                                <label class="block text-sm font-medium mb-2">Servicio</label>
                                <select id="serviceSelect" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white">
                                    <option value="">Selecciona un servicio</option>
                                </select>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium mb-2">URL de TikTok</label>
                                <input type="text" id="urlInput" placeholder="https://www.tiktok.com/@usuario/video/123" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white">
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium mb-2">Cantidad <span id="quantityRange" class="text-gray-400"></span></label>
                                <input type="number" id="quantityInput" placeholder="Cantidad" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white">
                                <p id="costEstimate" class="text-sm text-gray-400 mt-2 hidden">Costo: $<span id="costAmount">0</span></p>
                            </div>

                            <div id="errorMessage" class="hidden mb-4 p-3 bg-red-500/20 border border-red-500/30 rounded-lg text-sm"></div>

                            <button id="submitButton" onclick="createOrder()" class="w-full bg-gradient-to-r from-purple-500 to-pink-500 py-4 rounded-xl font-semibold text-lg">
                                Crear Pedido
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="bg-white/5 backdrop-blur-xl rounded-2xl border border-white/10 p-6">
                        <h3 class="font-semibold mb-4">Historial de Pedidos</h3>
                        <div id="orderHistory" class="space-y-3">
                            <p class="text-sm text-gray-400 text-center py-8">No hay pedidos aún</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let services = [];
        let selectedService = null;

        <?php if ($hasApiKey): ?>
        window.addEventListener('DOMContentLoaded', () => {
            loadServices();
            loadBalance();
            loadOrderHistory();
        });
        <?php endif; ?>

        async function saveApiKey() {
            const apiKey = document.getElementById('apiKeyInput').value.trim();
            if (!apiKey) return alert('Ingresa tu API Key');

            const formData = new FormData();
            formData.append('ajax', 'save_api_key');
            formData.append('api_key', apiKey);

            const response = await fetch('', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) location.reload();
        }

        function showSettings() {
            document.getElementById('settingsScreen').classList.remove('hidden');
            document.getElementById('mainScreen').classList.add('hidden');
        }

        async function loadServices() {
            const formData = new FormData();
            formData.append('ajax', 'get_services');

            const response = await fetch('', { method: 'POST', body: formData });
            services = await response.json();

            const select = document.getElementById('serviceSelect');
            select.innerHTML = '<option value="">Selecciona un servicio</option>';
            
            services.forEach(s => {
                const option = document.createElement('option');
                option.value = s.service;
                option.textContent = `${s.name} - $${s.rate}/1000 (${s.min}-${s.max})`;
                option.dataset.service = JSON.stringify(s);
                select.appendChild(option);
            });

            document.getElementById('servicesCount').textContent = services.length;

            select.onchange = (e) => {
                if (e.target.value) {
                    selectedService = JSON.parse(e.target.options[e.target.selectedIndex].dataset.service);
                    document.getElementById('quantityRange').textContent = `(${selectedService.min} - ${selectedService.max})`;
                } else {
                    selectedService = null;
                }
                updateCost();
            };
        }

        async function loadBalance() {
            const formData = new FormData();
            formData.append('ajax', 'get_balance');

            const response = await fetch('', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.balance) {
                document.getElementById('balanceDisplay').querySelector('span').textContent = '$' + parseFloat(data.balance).toFixed(2);
            }
        }

        document.getElementById('quantityInput')?.addEventListener('input', updateCost);

        function updateCost() {
            const quantity = parseInt(document.getElementById('quantityInput').value);
            if (selectedService && quantity) {
                const cost = (selectedService.rate * quantity / 1000).toFixed(2);
                document.getElementById('costAmount').textContent = cost;
                document.getElementById('costEstimate').classList.remove('hidden');
            } else {
                document.getElementById('costEstimate').classList.add('hidden');
            }
        }

        async function createOrder() {
            const url = document.getElementById('urlInput').value.trim();
            const quantity = parseInt(document.getElementById('quantityInput').value);

            if (!url || !selectedService || !quantity) {
                return showError('Completa todos los campos');
            }

            if (quantity < selectedService.min || quantity > selectedService.max) {
                return showError(`Cantidad debe estar entre ${selectedService.min} y ${selectedService.max}`);
            }

            const btn = document.getElementById('submitButton');
            btn.disabled = true;
            btn.innerHTML = 'Procesando...';

            const formData = new FormData();
            formData.append('ajax', 'create_order');
            formData.append('service', selectedService.service);
            formData.append('service_name', selectedService.name);
            formData.append('link', url);
            formData.append('quantity', quantity);
            formData.append('cost', (selectedService.rate * quantity / 1000).toFixed(2));

            const response = await fetch('', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.order) {
                document.getElementById('orderIdDisplay').textContent = data.order;
                document.getElementById('orderForm').classList.add('hidden');
                document.getElementById('successView').classList.remove('hidden');

                setTimeout(() => {
                    document.getElementById('successView').classList.add('hidden');
                    document.getElementById('orderForm').classList.remove('hidden');
                    document.getElementById('urlInput').value = '';
                    document.getElementById('quantityInput').value = '';
                    document.getElementById('serviceSelect').value = '';
                    selectedService = null;
                }, 5000);

                loadBalance();
                loadOrderHistory();
            } else {
                showError(data.error || 'Error al crear pedido');
            }

            btn.disabled = false;
            btn.innerHTML = 'Crear Pedido';
        }

        async function loadOrderHistory() {
            const formData = new FormData();
            formData.append('ajax', 'get_order_history');

            const response = await fetch('', { method: 'POST', body: formData });
            const orders = await response.json();

            const container = document.getElementById('orderHistory');
            if (!orders.length) {
                container.innerHTML = '<p class="text-sm text-gray-400 text-center py-8">No hay pedidos aún</p>';
                return;
            }

            container.innerHTML = orders.slice(0, 10).map(o => `
                <div class="bg-white/5 rounded-lg p-3 border border-white/10">
                    <div class="flex justify-between mb-2">
                        <span class="text-sm font-medium">#${o.id}</span>
                        <span class="text-xs text-gray-400">$${o.cost}</span>
                    </div>
                    <p class="text-xs text-gray-400">${o.service}</p>
                    <p class="text-xs text-gray-500 truncate">${o.link}</p>
                    <div class="flex justify-between mt-2">
                        <span class="text-xs text-purple-400">${o.quantity} unidades</span>
                        <span class="text-xs text-gray-500">${o.date.split(' ')[0]}</span>
                    </div>
                </div>
            `).join('');
        }

        function showError(msg) {
            const div = document.getElementById('errorMessage');
            div.textContent = msg;
            div.classList.remove('hidden');
            setTimeout(() => div.classList.add('hidden'), 5000);
        }
    </script>
</body>
</html>
