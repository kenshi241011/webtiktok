<?php
session_start();

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

    public function status($order_id)
    {
        return json_decode($this->connect([
            'key' => $this->api_key,
            'action' => 'status',
            'order' => $order_id
        ]), true);
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

        if (is_array($post)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, join('&', $_post));
        }
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $result = curl_exec($ch);
        if (curl_errno($ch) != 0 && empty($result)) {
            $result = false;
        }
        curl_close($ch);
        return $result;
    }
}

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['ajax'];
    
    if ($action === 'save_api_key') {
        $_SESSION['api_key'] = $_POST['api_key'];
        echo json_encode(['success' => true]);
        exit;
    }
    
    if (!isset($_SESSION['api_key'])) {
        echo json_encode(['error' => 'API Key no configurada']);
        exit;
    }
    
    $api = new PeakerrAPI($_SESSION['api_key']);
    
    switch ($action) {
        case 'get_services':
            $services = $api->services();
            // Filtrar solo TikTok
            $tiktok_services = array_filter($services, function($s) {
                $name = strtolower($s['name'] ?? '');
                $category = strtolower($s['category'] ?? '');
                return strpos($name, 'tiktok') !== false || strpos($category, 'tiktok') !== false;
            });
            echo json_encode(array_values($tiktok_services));
            break;
            
        case 'get_balance':
            echo json_encode($api->balance());
            break;
            
        case 'create_order':
            $result = $api->order([
                'service' => $_POST['service'],
                'link' => $_POST['link'],
                'quantity' => $_POST['quantity']
            ]);
            
            if (isset($result['order'])) {
                // Guardar en historial
                if (!isset($_SESSION['orders'])) {
                    $_SESSION['orders'] = [];
                }
                array_unshift($_SESSION['orders'], [
                    'id' => $result['order'],
                    'service' => $_POST['service_name'],
                    'link' => $_POST['link'],
                    'quantity' => $_POST['quantity'],
                    'cost' => $_POST['cost'],
                    'date' => date('Y-m-d H:i:s')
                ]);
                // Mantener solo los últimos 50 pedidos
                $_SESSION['orders'] = array_slice($_SESSION['orders'], 0, 50);
            }
            
            echo json_encode($result);
            break;
            
        case 'get_order_history':
            echo json_encode($_SESSION['orders'] ?? []);
            break;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TikBoost Pro - Impulsa tu TikTok</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        .gradient-text {
            background: linear-gradient(to right, #a78bfa, #f472b6, #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-purple-900 to-gray-900 text-white min-h-screen">

    <!-- Pantalla de configuración -->
    <div id="settingsScreen" class="<?php echo isset($_SESSION['api_key']) ? 'hidden' : ''; ?> min-h-screen flex items-center justify-center p-4">
        <div class="bg-white/10 backdrop-blur-xl rounded-2xl border border-white/20 p-8 max-w-md w-full">
            <div class="flex items-center gap-3 mb-6">
                <svg class="w-8 h-8 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <h2 class="text-2xl font-bold">Configuración API</h2>
            </div>
            
            <p class="text-gray-300 mb-4">
                Ingresa tu API Key de Peakerr para comenzar a usar el servicio.
            </p>
            
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">API Key</label>
                <input
                    type="password"
                    id="apiKeyInput"
                    placeholder="Ingresa tu API Key"
                    class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl focus:border-purple-500 focus:outline-none text-white"
                />
                <p class="text-xs text-gray-400 mt-2">
                    Obtén tu API Key en: peakerr.com/account
                </p>
            </div>

            <button
                onclick="saveApiKey()"
                class="w-full bg-gradient-to-r from-purple-500 to-pink-500 py-3 rounded-xl font-semibold hover:from-purple-600 hover:to-pink-600 transition"
            >
                Guardar y Continuar
            </button>
        </div>
    </div>

    <!-- Pantalla principal -->
    <div id="mainScreen" class="<?php echo !isset($_SESSION['api_key']) ? 'hidden' : ''; ?>">
        <!-- Header -->
        <header class="border-b border-white/10 backdrop-blur-sm bg-black/20">
            <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <span class="text-xl font-bold">TikBoost Pro</span>
                </div>
                <div class="flex items-center gap-4">
                    <div id="balanceDisplay" class="flex items-center gap-2 bg-green-500/20 border border-green-500/30 rounded-lg px-3 py-1.5">
                        <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-sm font-medium">$0.00</span>
                    </div>
                    <button onclick="showSettings()" class="p-2 hover:bg-white/10 rounded-lg transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <div class="max-w-6xl mx-auto px-4 py-16">
            <div class="text-center mb-12">
                <h1 class="text-5xl font-bold mb-4 gradient-text">
                    Impulsa tu TikTok
                </h1>
                <p class="text-xl text-gray-300">
                    <span id="servicesCount">0</span> servicios disponibles - Entrega instantánea
                </p>
            </div>

            <div class="grid lg:grid-cols-3 gap-8">
                <!-- Panel principal -->
                <div class="lg:col-span-2">
                    <div class="bg-white/5 backdrop-blur-xl rounded-2xl border border-white/10 p-8">
                        <!-- Vista de éxito -->
                        <div id="successView" class="hidden text-center py-12">
                            <div class="w-20 h-20 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-10 h-10 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold mb-2">¡Pedido creado!</h3>
                            <p class="text-gray-300 mb-4">
                                ID del pedido: #<span id="orderIdDisplay"></span>
                            </p>
                            <p class="text-sm text-gray-400">
                                Tu pedido está siendo procesado. Verás los resultados pronto.
                            </p>
                        </div>

                        <!-- Formulario principal -->
                        <div id="orderForm">
                            <div class="mb-6">
                                <label class="block text-sm font-medium mb-3">Tipo de servicio</label>
                                <div class="grid grid-cols-3 gap-3">
                                    <div class="p-4 rounded-xl border border-white/10 bg-white/5 text-center">
                                        <svg class="w-6 h-6 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                        </svg>
                                        <span class="text-sm font-medium block">Seguidores</span>
                                        <span class="text-xs text-gray-400" id="followersCount">0 servicios</span>
                                    </div>
                                    <div class="p-4 rounded-xl border border-white/10 bg-white/5 text-center">
                                        <svg class="w-6 h-6 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                        </svg>
                                        <span class="text-sm font-medium block">Likes</span>
                                        <span class="text-xs text-gray-400" id="likesCount">0 servicios</span>
                                    </div>
                                    <div class="p-4 rounded-xl border border-white/10 bg-white/5 text-center">
                                        <svg class="w-6 h-6 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        <span class="text-sm font-medium block">Vistas</span>
                                        <span class="text-xs text-gray-400" id="viewsCount">0 servicios</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium mb-2">Servicio específico</label>
                                <select
                                    id="serviceSelect"
                                    class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl focus:border-purple-500 focus:outline-none text-white"
                                >
                                    <option value="">Selecciona un servicio</option>
                                </select>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium mb-2">URL de TikTok</label>
                                <input
                                    type="text"
                                    id="urlInput"
                                    placeholder="https://www.tiktok.com/@usuario/video/123456789"
                                    class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl focus:border-purple-500 focus:outline-none text-white"
                                />
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium mb-2">
                                    Cantidad <span id="quantityRange" class="text-gray-400"></span>
                                </label>
                                <input
                                    type="number"
                                    id="quantityInput"
                                    placeholder="Cantidad"
                                    class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl focus:border-purple-500 focus:outline-none text-white"
                                />
                                <p id="costEstimate" class="text-sm text-gray-400 mt-2 hidden">
                                    Costo estimado: $<span id="costAmount">0.00</span> USD
                                </p>
                            </div>

                            <div id="errorMessage" class="hidden mb-4 p-3 bg-red-500/20 border border-red-500/30 rounded-lg text-red-300 text-sm"></div>

                            <button
                                id="submitButton"
                                onclick="createOrder()"
                                class="w-full bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 disabled:from-gray-500 disabled:to-gray-600 disabled:cursor-not-allowed py-4 rounded-xl font-semibold text-lg transition-all"
                            >
                                Crear Pedido
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Historial -->
                <div>
                    <div class="bg-white/5 backdrop-blur-xl rounded-2xl border border-white/10 p-6">
                        <h3 class="font-semibold mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Historial de Pedidos
                        </h3>
                        <div id="orderHistory" class="space-y-3 max-h-96 overflow-y-auto">
                            <p class="text-sm text-gray-400 text-center py-8">
                                No hay pedidos aún
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let services = [];
        let selectedService = null;

        <?php if (isset($_SESSION['api_key'])): ?>
        window.addEventListener('DOMContentLoaded', () => {
            loadServices();
            loadBalance();
            loadOrderHistory();
        });
        <?php endif; ?>

        async function saveApiKey() {
            const apiKey = document.getElementById('apiKeyInput').value.trim();
            if (!apiKey) {
                alert('Por favor ingresa tu API Key');
                return;
            }

            const formData = new FormData();
            formData.append('ajax', 'save_api_key');
            formData.append('api_key', apiKey);

            const response = await fetch('', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                location.reload();
            }
        }

        function showSettings() {
            document.getElementById('settingsScreen').classList.remove('hidden');
            document.getElementById('mainScreen').classList.add('hidden');
        }

        async function loadServices() {
            try {
                const formData = new FormData();
                formData.append('ajax', 'get_services');

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                services = await response.json();
                console.log('Servicios TikTok:', services);

                populateServiceSelect();
                updateServiceCounts();
                document.getElementById('servicesCount').textContent = services.length;
            } catch (err) {
                showError('Error al cargar servicios: ' + err.message);
            }
        }

        async function loadBalance() {
            try {
                const formData = new FormData();
                formData.append('ajax', 'get_balance');

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.balance) {
                    document.getElementById('balanceDisplay').querySelector('span').textContent = '$' + parseFloat(data.balance).toFixed(2);
                }
            } catch (err) {
                console.error('Error al cargar balance:', err);
            }
        }

        function populateServiceSelect() {
            const select = document.getElementById('serviceSelect');
            select.innerHTML = '<option value="">Selecciona un servicio</option>';
            
            services.forEach(service => {
                const option = document.createElement('option');
                option.value = service.service;
                option.textContent = `${service.name} - $${service.rate}/1000 (Min: ${service.min}, Max: ${service.max})`;
                option.dataset.service = JSON.stringify(service);
                select.appendChild(option);
            });

            select.addEventListener('change', (e) => {
                if (e.target.value) {
                    selectedService = JSON.parse(e.target.options[e.target.selectedIndex].dataset.service);
                    document.getElementById('quantityRange').textContent = `(${selectedService.min} - ${selectedService.max})`;
                    updateCostEstimate();
                } else {
                    selectedService = null;
                    document.getElementById('quantityRange').textContent = '';
                    document.getElementById('costEstimate').classList.add('hidden');
                }
            });
        }

        function updateServiceCounts() {
            const followers = services.filter(s => s.name.toLowerCase().includes('follower')).length;
            const likes = services.filter(s => s.name.toLowerCase().includes('like')).length;
            const views = services.filter(s => s.name.toLowerCase().includes('view')).length;

            document.getElementById('followersCount').textContent = `${followers} servicios`;
            document.getElementById('likesCount').textContent = `${likes} servicios`;
            document.getElementById('viewsCount').textContent = `${views} servicios`;
        }

        document.getElementById('quantityInput')?.addEventListener('input', updateCostEstimate);

        function updateCostEstimate() {
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
            const submitBtn = document.getElementById('submitButton');

            if (!url) {
                showError('Por favor ingresa una URL de TikTok');
                return;
            }

            if (!selectedService) {
                showError('Por favor selecciona un servicio');
                return;
            }

            if (!quantity) {
                showError('Por favor ingresa una cantidad');
                return;
            }

            if (quantity < parseInt(selectedService.min) || quantity > parseInt(selectedService.max)) {
                showError(`La cantidad debe estar entre ${selectedService.min} y ${selectedService.max}`);
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="flex items-center justify-center gap-2"><div class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>Procesando...</span>';

            try {
                const formData = new FormData();
                formData.append('ajax', 'create_order');
                formData.append('service', selectedService.service);
                formData.append('service_name', selectedService.name);
                formData.append('link', url);
                formData.append('quantity', quantity);
                formData.append('cost', (selectedService.rate * quantity / 1000).toFixed(2));

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

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
                        document.getElementById('quantityRange').textContent = '';
                        document.getElementById('costEstimate').classList.add('hidden');
                    }, 5000);

                    loadBalance();
                    loadOrderHistory();
                } else if (data.error) {
                    showError('Error: ' + data.error);
                }
            } catch (err) {
                showError('Error al crear pedido: ' + err.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Crear Pedido';
            }
        }

        async function loadOrderHistory() {
            try {
                const formData = new FormData();
                formData.append('ajax', 'get_order_history');

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const orders = await response.json();
                renderOrderHistory(orders);
            } catch (err) {
                console.error('Error al cargar historial:', err);
            }
        }

        function renderOrderHistory(orders) {
            const container = document.getElementById('orderHistory');
            
            if (!orders || orders.length === 0) {
                container.innerHTML = '<p class="text-sm text-gray-400 text-center py-8">No hay pedidos aún</p>';
                return;
            }

            container.innerHTML = orders.slice(0, 10).map(order => `
                <div class="bg-white/5 rounded-lg p-3 border border-white/10">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-sm font-medium">#${order.id}</span>
                        <span class="text-xs text-gray-400">${order.cost}</span>
                    </div>
                    <p class="text-xs text-gray-400 mb-1">${order.service}</p>
                    <p class="text-xs text-gray-500 truncate">${order.link}</p>
                    <div class="flex justify-between items-center mt-2">
                        <span class="text-xs text-purple-400">${order.quantity} unidades</span>
                        <span class="text-xs text-gray-500">${order.date.split(' ')[0]}</span>
                    </div>
                </div>
            `).join('');
        }

        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.classList.remove('hidden');
            
            setTimeout(() => {
                errorDiv.classList.add('hidden');
            }, 5000);
        }
    </script>
</body>
</html>