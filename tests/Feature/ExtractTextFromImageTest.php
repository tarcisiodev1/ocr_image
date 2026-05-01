<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Actions\ExtractTextFromImage;
use RuntimeException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function getFakeImagePathAction(): string
{
    $path = sys_get_temp_dir() . '/action_test_image_' . uniqid() . '.png';
    file_put_contents($path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
    ));
    return $path;
}

// Suíte de Testes Diretos Integrados para o motor principal do projeto (A Action Invocável!)
describe('Action ExtractTextFromImage - Validações do Motor Principal', function () {

    it('deve extrair e limpar texto enviado pelo LLM (sem necessidade de comandos CLI)', function () {
        // Arrange
        $dirtyText = "\x{200B}Hello\r\n\r\n\x{FEFF}World\n\n\n\nDirect Test";
        $expected = "Hello\n\nWorld\n\nDirect Test";
        
        $imagePath = getFakeImagePathAction();
        
        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => $dirtyText]]
            ], 200)
        ]);

        // Act - Sem comandos lentos de "Artisan::call(...)". Instanciamos o objeto maravilhosamente:
        $action = new ExtractTextFromImage();
        $resultadoExtracao = $action($imagePath); // Invoca a classe

        // Assert - A string final validada na nossa mão:
        expect($resultadoExtracao)->toBe($expected);
    });

    it('deve rejeitar uma imagem inexistente retornando um Erro do PHP nativo (RuntimeException)', function () {
        // Sem Http fake porque o sistema deve estourar o erro antes mesmo de chegar na rede.
        $action = new ExtractTextFromImage();

        $pathInexistente = '/caminho/completamente/invalido/para/foto.jpeg';

        // PEST syntax: "Eu espero que essa closure estoure um RuntimeException."
        expect(fn() => $action($pathInexistente))->toThrow(
            RuntimeException::class, 
            "Image file not found: {$pathInexistente}"
        );
    });

    it('deve enviar a requisição formatada perfeitamente de acordo com parametros do LLM Studio API', function () {
        // Arrange
        $capturedRequest = null;
        $imagePath = getFakeImagePathAction();
        
        Http::fake([
            '*' => function ($request) use (&$capturedRequest) {
                $capturedRequest = $request;
                return Http::response(['choices' => [['message' => ['text' => 'Ok!']]]], 200);
            }
        ]);

        // Act
        // Só rodamos o invoke.
        $action = new ExtractTextFromImage();
        $action($imagePath);

        // Assert - Avaliamos se ele fez o envio interno real (sem testar commands).
        $payload = json_decode($capturedRequest->body(), true);
        expect($payload['model'])->toBe(config('services.lmstudio.model'));
        expect($payload['temperature'])->toBe(0.1);
        expect($payload['max_tokens'])->toBe(4000);
    });

    it('deve propagar a falha 500 do servidor levantando erro transparente de falha da API', function () {
        // Arrange
        $imagePath = getFakeImagePathAction();
        
        Http::fake([
            '*' => Http::response(['error' => ['message' => 'Internal Server Failure']], 500)
        ]);

        $action = new ExtractTextFromImage();

        // Assert
        expect(fn() => $action($imagePath))->toThrow(
            RuntimeException::class,
            'OCR API request failed: Internal Server Failure' // Nossa action formata assim
        );
    });
});
