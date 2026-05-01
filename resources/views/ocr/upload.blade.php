<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR - Extração de Texto</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .code-block {
            font-family: 'Fira Code', 'Monaco', 'Consolas', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 1rem;
            border-radius: 0.5rem;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.6;
            tab-size: 4;
            max-height: 60vh;
            overflow-y: auto;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <h1 class="text-3xl font-bold text-gray-800 mb-8 text-center">OCR - Extração de Texto de Imagens</h1>

        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form id="upload-form" action="{{ route('ocr.extract') }}" method="POST" enctype="multipart/form-data"
                class="space-y-4">
                @csrf
                <div>
                    <label for="image" class="block text-gray-700 font-medium mb-2">Selecione uma imagem (PNG, JPEG,
                        JPG)</label>
                    <input type="file" id="image" name="image" accept="image/png,image/jpeg,image/jpg"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <button type="submit" id="submit-btn"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                    Extrair Texto
                </button>
            </form>
        </div>

        <div id="loading" class="hidden bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-center space-x-3">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <span class="text-gray-700">Processando imagem...</span>
            </div>
        </div>

        <div id="result" class="hidden bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-800">Texto Extraído</h2>
                <span id="status-badge" class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                    ✓ Concluído
                </span>
            </div>

            <div class="code-block" id="extracted-text"></div>

            <div class="flex flex-wrap gap-3 mt-4">
                <button id="btn-copy"
                    class="flex items-center gap-2 bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    <span>Copiar Texto</span>
                </button>

                <button id="btn-new"
                    class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Nova Extração</span>
                </button>
            </div>
        </div>

        <div id="error" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"
            role="alert">
            <span id="error-message"></span>
        </div>
    </div>

    <script>
        const uploadForm = document.getElementById('upload-form');
        const submitBtn = document.getElementById('submit-btn');
        const loadingDiv = document.getElementById('loading');
        const resultDiv = document.getElementById('result');
        const extractedText = document.getElementById('extracted-text');
        const btnCopy = document.getElementById('btn-copy');
        const btnNew = document.getElementById('btn-new');
        const errorDiv = document.getElementById('error');
        const errorMessage = document.getElementById('error-message');
        const statusBadge = document.getElementById('status-badge');

        let jobId = null;
        let pollingInterval = null;

        function showError(message) {
            errorMessage.textContent = message;
            errorDiv.classList.remove('hidden');
        }

        function hideError() {
            errorDiv.classList.add('hidden');
        }

        uploadForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(uploadForm);
            const imageFile = formData.get('image');

            if (!imageFile || imageFile.size === 0) {
                showError('Por favor, selecione uma imagem');
                return;
            }

            hideError();
            uploadForm.classList.add('hidden');
            loadingDiv.classList.remove('hidden');
            submitBtn.disabled = true;

            try {
                const response = await fetch('{{ route('ocr.extract') }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });

                const contentType = response.headers.get('content-type');
                if (!response.ok) {
                    if (contentType && contentType.includes('application/json')) {
                        const errorData = await response.json();
                        throw new Error(errorData.error || 'Upload failed');
                    }
                    throw new Error('Upload failed');
                }

                const data = await response.json();
                jobId = data.job_id || data.id;

                if (jobId) {
                    startPolling(jobId);
                }
            } catch (error) {
                showError('Erro ao fazer upload: ' + error.message);
                loadingDiv.classList.add('hidden');
                uploadForm.classList.remove('hidden');
                submitBtn.disabled = false;
            }
        });

        function startPolling(id) {
            pollingInterval = setInterval(async () => {
                try {
                    const response = await fetch(`/ocr/result/${id}`);
                    const data = await response.json();

                    if (data.status === 'completed') {
                        clearInterval(pollingInterval);
                        showResult(data);
                    } else if (data.status === 'failed') {
                        clearInterval(pollingInterval);
                        showError('Processamento falhou. Tente novamente.');
                        loadingDiv.classList.add('hidden');
                        uploadForm.classList.remove('hidden');
                        submitBtn.disabled = false;
                    }
                } catch (error) {
                    console.error('Polling error:', error);
                }
            }, 2000);
        }

        function showResult(data) {
            loadingDiv.classList.add('hidden');
            resultDiv.classList.remove('hidden');
            extractedText.textContent = data.extracted_text || '';
            statusBadge.className = 'px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium';
            statusBadge.textContent = '✓ Concluído';
        }

        // Função para resetar tudo
        function resetUI() {
            resultDiv.classList.add('hidden');
            loadingDiv.classList.add('hidden');
            uploadForm.classList.remove('hidden');
            uploadForm.reset();
            extractedText.textContent = '';
            jobId = null;
            submitBtn.disabled = false;
            hideError();

            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        }

        // Botão copiar
        btnCopy.addEventListener('click', function() {
            const text = extractedText.textContent;
            navigator.clipboard.writeText(text).then(function() {
                btnCopy.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Copiado!</span>
                `;
                setTimeout(function() {
                    btnCopy.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <span>Copiar Texto</span>
                    `;
                }, 2000);
            });
        });

        // Botão nova extração - limpa cache e reseta UI
        btnNew.addEventListener('click', async function() {
            btnNew.disabled = true;
            btnNew.innerHTML = `
                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Limpando...</span>
            `;

            try {
                // Limpa cache temporário (mantendo ID atual se existir)
                await fetch('{{ route('ocr.clear') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        keep_id: jobId
                    })
                });

                // Reseta interface
                resetUI();

            } catch (error) {
                console.error('Erro ao limpar cache:', error);
                resetUI(); // Com erro, reseta UI
            }

            btnNew.disabled = false;
            btnNew.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Nova Extração</span>
            `;
        });

        @if (session('job_id'))
            uploadForm.classList.add('hidden');
            loadingDiv.classList.remove('hidden');
            startPolling({{ session('job_id') }});
        @endif
    </script>
</body>

</html>
