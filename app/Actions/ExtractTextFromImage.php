<?php

namespace App\Actions;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExtractTextFromImage
{
    /**
     * Extrai texto limpo de uma imagem usando LLM via LM Studio.
     * 
     * @param string $imagePath
     * @return string
     * @throws RuntimeException
     */
    public function __invoke(string $imagePath): string
    {
        if (!file_exists($imagePath)) {
            throw new RuntimeException("Image file not found: {$imagePath}");
        }

        $imageBase64 = base64_encode(file_get_contents($imagePath));
        $mime = $this->getMimeType($imagePath);

        $payload = [
            'model' => config('services.lmstudio.model'),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Extraia TODO o texto visível. Mantenha quebras de linha, tabulações e estrutura original. NÃO adicione comentários, NÃO resuma, NÃO interprete. Retorne APENAS o texto bruto.'
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$mime};base64,{$imageBase64}"
                            ]
                        ]
                    ]
                ]
            ],
            'max_tokens' => 4000,
            'temperature' => 0.1,
            'stop' => ["\n\n\n"]
        ];

        $response = Http::timeout(30)
            ->withoutVerifying()
            ->post(config('services.lmstudio.base_url') . '/v1/chat/completions', $payload);

        if ($response->failed()) {
            // O comando CLI e os logs dependem do formato de erro original
            $body = $response->body();
            $data = json_decode($body, true);
            $errorMsg = $data['error']['message'] ?? $body;
            throw new RuntimeException('OCR API request failed: ' . $errorMsg);
        }

        $data = $response->json();
        if (json_last_error() !== JSON_ERROR_NONE) {
            $data = ['choices' => [['message' => $response->body()]]];
        }

        if (!isset($data['choices'][0]['message'])) {
            throw new RuntimeException('Unexpected response format from OCR API');
        }

        $content = $data['choices'][0]['message'];

        if (is_string($content)) {
            return $this->sanitize($content);
        } elseif (is_array($content)) {
            if (isset($content['content'])) {
                return $this->sanitize($content['content']);
            } elseif (isset($content['text'])) {
                return $this->sanitize($content['text']);
            }
        }

        throw new RuntimeException('Unexpected content format from OCR API');
    }

    protected function getMimeType(string $filePath): string
    {
        $mime = mime_content_type($filePath);
        return in_array($mime, ['image/png', 'image/jpeg', 'image/jpg', 'image/webp']) ? $mime : 'image/png';
    }

    protected function sanitize(string $text): string
    {
        $text = str_replace(["\x{200B}", "\x{200C}", "\x{200D}", "\x{FEFF}"], '', $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        return $text;
    }
}
