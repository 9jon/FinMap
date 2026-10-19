<?php
// Copie para config/ai.local.php em desenvolvimento.
// Em produção, prefira definir essas variáveis no painel do host.
return [
    'ollama_url' => getenv('FINMAP_OLLAMA_URL') ?: 'http://127.0.0.1:11434',
    'ollama_model' => getenv('FINMAP_OLLAMA_MODEL') ?: 'qwen3:4b-instruct',
    'ollama_api_key' => getenv('FINMAP_OLLAMA_API_KEY') ?: '',
];