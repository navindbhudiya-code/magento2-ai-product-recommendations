<?php
/**
 * NavinDBhudiya ProductRecommendation
 *
 * @category  NavinDBhudiya
 * @package   NavinDBhudiya_ProductRecommendation
 * @author    Navin Bhudiya
 * @license   MIT License
 */

declare(strict_types=1);

namespace NavinDBhudiya\ProductRecommendation\Test\Unit\Service\Embedding;

use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NavinDBhudiya\ProductRecommendation\Helper\Config;
use NavinDBhudiya\ProductRecommendation\Service\Embedding\ApiEmbeddingProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the hosted (OpenAI-style) embedding provider, using a real Guzzle client
 * driven by MockHandler so no live API is called. The GuzzleHttp\ClientFactory stub is
 * provided by Test/Unit/bootstrap.php.
 */
class ApiEmbeddingProviderTest extends TestCase
{
    /**
     * @var Config|MockObject
     */
    private $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getApiEmbeddingModel')->willReturn('text-embedding-3-small');
        $this->config->method('getApiEmbeddingDimension')->willReturn(1536);
        $this->config->method('getApiEmbeddingEndpoint')->willReturn('https://api.openai.com/v1/embeddings');
    }

    private function providerReturning(Response $response): ApiEmbeddingProvider
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([$response]))]);
        $factory = $this->createMock(ClientFactory::class);
        $factory->method('create')->willReturn($client);

        return new ApiEmbeddingProvider($factory, $this->config, $this->createMock(LoggerInterface::class));
    }

    public function testGetNameAndDimension(): void
    {
        $provider = $this->providerReturning(new Response(200, [], '{"data":[]}'));
        $this->assertSame('api', $provider->getName());
        $this->assertSame(1536, $provider->getDimension());
    }

    public function testIsAvailableTracksApiKey(): void
    {
        $this->config->method('getApiEmbeddingKey')->willReturn('sk-test');
        $provider = $this->providerReturning(new Response(200, [], '{"data":[]}'));
        $this->assertTrue($provider->isAvailable());
    }

    public function testReturnsVectorsOfConfiguredDimensionInInputOrder(): void
    {
        $this->config->method('getApiEmbeddingKey')->willReturn('sk-test');

        $vecA = array_fill(0, 1536, 0.01);
        $vecB = array_fill(0, 1536, 0.02);
        // Returned out of order to prove we sort by index.
        $body = json_encode(['data' => [
            ['index' => 1, 'embedding' => $vecB],
            ['index' => 0, 'embedding' => $vecA],
        ]]);

        $provider = $this->providerReturning(new Response(200, [], $body));
        $result = $provider->generateEmbeddings(['first', 'second']);

        $this->assertCount(2, $result);
        $this->assertCount(1536, $result[0]);
        $this->assertSame(0.01, $result[0][0]); // index 0 came back first
        $this->assertSame(0.02, $result[1][0]);
    }

    public function testGenerateSingleEmbedding(): void
    {
        $this->config->method('getApiEmbeddingKey')->willReturn('sk-test');
        $body = json_encode(['data' => [['index' => 0, 'embedding' => [0.5, 0.6, 0.7]]]]);
        $provider = $this->providerReturning(new Response(200, [], $body));

        $this->assertSame([0.5, 0.6, 0.7], $provider->generateEmbedding('hello'));
    }

    public function testMissingKeyReturnsEmptyWithoutCallingApi(): void
    {
        $this->config->method('getApiEmbeddingKey')->willReturn('');
        $provider = $this->providerReturning(new Response(200, [], '{"data":[]}'));
        $this->assertFalse($provider->isAvailable());
        $this->assertSame([], $provider->generateEmbeddings(['x']));
    }

    public function testMalformedResponseReturnsEmpty(): void
    {
        $this->config->method('getApiEmbeddingKey')->willReturn('sk-test');
        $provider = $this->providerReturning(new Response(200, [], '{"unexpected":true}'));
        $this->assertSame([], $provider->generateEmbeddings(['x']));
    }

    public function testEmptyInputShortCircuits(): void
    {
        $provider = $this->providerReturning(new Response(200, [], '{"data":[]}'));
        $this->assertSame([], $provider->generateEmbeddings([]));
    }
}
