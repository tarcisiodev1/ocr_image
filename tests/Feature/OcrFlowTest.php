<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\OcrExtraction;

uses(TestCase::class, RefreshDatabase::class);

describe('Rotas e Base de Dados de Extracões OCR (Ocr Extraction)', function () {

    it('deve criar corretamente um registro inicial de extração no banco de dados', function () {
        // Arrange (Preparação)
        // Usamos fake() no storage do Laravel para não criar pastas temporárias de forma irremediável
        Storage::fake('local');

        // Act (Ação)
        // Inserimos um item de teste via Model (Eloquent) 
        $ocrExtraction = OcrExtraction::create([
            'filename' => 'test.png',
            'original_path' => '/tmp/test.png',
            'status' => 'pending'
        ]);

        // Assert (Verificação)
        // O código não deve ser null e manter as informações corretas de inicio do processamento de fila
        expect($ocrExtraction->id)->not->toBeNull()
            ->and($ocrExtraction->filename)->toBe('test.png')
            ->and($ocrExtraction->status)->toBe('pending');
    });

    it('deve retornar os detalhes e texto pronto da rota Get /ocr/result', function () {
        // Arrange: "Dado que já exista uma extração Concluída na base:"
        $ocrExtraction = OcrExtraction::create([
            'filename' => 'test.png',
            'original_path' => '/tmp/test.png',
            'extracted_text' => 'Texto final da nota fiscal gerado',
            'status' => 'completed'
        ]);

        // Act: "Quando eu fizer uma requisição via Browser / Postman nessa rota..."
        $response = $this->get("/ocr/result/{$ocrExtraction->id}");

        // Assert: "Então a resposta deve ser Positiva (200) e os dados visíveis em JSON estruturado"
        $response->assertStatus(200);
        $response->assertJson([
            'id' => $ocrExtraction->id,
            'status' => 'completed',
            'extracted_text' => 'Texto final da nota fiscal gerado'
        ]);
    });

    it('deve esconder o resultado JSON caso a extração ainda esteja em processamento', function () {
        // Arrange: O modelo foi gravado com status processando e string null
        $ocrExtraction = OcrExtraction::create([
            'filename' => 'test.png',
            'original_path' => '/tmp/test.png',
            'status' => 'processing'
        ]);

        // Act: Chamada HTTP
        $response = $this->get("/ocr/result/{$ocrExtraction->id}");

        // Assert: Valida que a resposta não quebra a tela do usuário (continua 200 HTTP)
        // porém a chave "extracted_text" volta como nula (null) enquanto ele não encerra
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'processing',
            'extracted_text' => null
        ]);
    });

    it('deve retornar status fail caso tenha ocorrido algum problema e a fila não terminar', function () {
        // Arrange
        $ocrExtraction = OcrExtraction::create([
            'filename' => 'test.png',
            'original_path' => '/tmp/test.png',
            'status' => 'failed'
        ]);

        // Act
        $response = $this->get("/ocr/result/{$ocrExtraction->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'failed'
        ]);
    });

    it('deve processar o Job de Fila com sucesso e alterar o dado no banco de pending para completed', function () {
        // Arrange
        // (Aqui reciclamos o mock de imagem do nosso outro arquivo por conveniencia)
        $imagePath = \Tests\Feature\getFakeImagePathAction();
        
        $ocrExtraction = OcrExtraction::create([
            'filename' => 'test.png',
            'original_path' => $imagePath,
            'status' => 'pending'
        ]);

        // Falsifica a conexão HTTP para a Action não quebrar no ar
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response(['choices' => [['message' => ['text' => 'Job Extraido Com Sucesso!']]]], 200)
        ]);

        // Act - Fica igual ao processamento do Redis/RabbitMQ, acionando a classe Job manualmente!
        $job = new \App\Jobs\ProcessOcrJob($ocrExtraction);
        // O Job injeta a Action no handle dele como montamos antes
        $job->handle(new \App\Actions\ExtractTextFromImage());

        // Assert - Busca o item do banco de dados novamente para validar a persistência.
        $ocrExtraction->refresh();

        expect($ocrExtraction->status)->toBe('completed');
        expect($ocrExtraction->extracted_text)->toBe('Job Extraido Com Sucesso!');
    });
});