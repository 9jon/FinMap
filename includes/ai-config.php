<?php
function finmapAiConfig(): array
{
    static $config = null;
    if ($config !== null) return $config;

    $local = [];
    $localFile = __DIR__ . '/../config/ai.local.php';
    if (is_file($localFile)) {
        $loaded = require $localFile;
        if (is_array($loaded)) $local = $loaded;
    }

    $env = static function (string $name, $fallback) {
        $value = getenv($name);
        return $value !== false && trim($value) !== '' ? trim($value) : $fallback;
    };

    $config = [
        'ollama_url' => rtrim((string)$env('FINMAP_OLLAMA_URL', $local['ollama_url'] ?? 'http://127.0.0.1:11434'), '/'),
        'ollama_model' => (string)$env('FINMAP_OLLAMA_MODEL', $local['ollama_model'] ?? 'qwen3:4b-instruct'),
        'ollama_api_key' => (string)$env('FINMAP_OLLAMA_API_KEY', $local['ollama_api_key'] ?? ''),
    ];
    return $config;
}

function finmapOllamaHeaders(): array
{
    $headers = ['Content-Type: application/json'];
    $key = finmapAiConfig()['ollama_api_key'];
    if ($key !== '') $headers[] = 'Authorization: Bearer ' . $key;
    return $headers;
}