<?php declare(strict_types=1);

namespace ExternalOrders\Tests\Service;

use ExternalOrders\Service\TopmSan6Client;
use ExternalOrders\Service\TopmSan6OrderMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TopmSan6ClientTest extends TestCase
{
    public function testFetchOrdersBuildsTopmUrlAndParsesXml(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('<response><orders><order><auftragsnummer>A-100</auftragsnummer></order></orders></response>');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->callback(static function (string $url): bool {
                    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

                    return ($params['funktion'] ?? null) === 'API-AUFTRAEGE'
                        && ($params['ssid'] ?? null) === 'abc'
                        && ($params['authentifizierung'] ?? null) === 'secret';
                }),
                ['timeout' => 2.5]
            )
            ->willReturn($response);

        $logger = new TopmInMemoryLogger();
        $client = new TopmSan6Client($httpClient, $logger, new TopmSan6OrderMapper());

        $result = $client->fetchOrders('https://example.test/api?ssid=abc&company=fms&product=sw&mandant=1&sys=live', 'secret', 2.5);

        static::assertSame('A-100', $result['orders'][0]['externalId'] ?? null);
        static::assertSame('san6', $result['orders'][0]['channel'] ?? null);
    }

    public function testFetchOrdersLogsXmlErrorAndReturnsEmptyOrders(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->with(false)->willReturn('<broken>');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $logger = new TopmInMemoryLogger();
        $client = new TopmSan6Client($httpClient, $logger, new TopmSan6OrderMapper());

        $result = $client->fetchOrders('https://example.test/api?ssid=abc&company=fms&product=sw&mandant=1&sys=live', 'secret', 0);

        static::assertSame([], $result['orders']);
        static::assertTrue($logger->hasRecord('error', 'TopM san6 XML parsing failed.'));
    }


    public function testFetchOrdersUsesCustomReadFunctionWhenProvided(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('<response><orders><order><auftragsnummer>A-200</auftragsnummer></order></orders></response>');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->callback(static function (string $url): bool {
                    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

                    return ($params['funktion'] ?? null) === 'API-CUSTOM-READ';
                }),
                []
            )
            ->willReturn($response);

        $logger = new TopmInMemoryLogger();
        $client = new TopmSan6Client($httpClient, $logger, new TopmSan6OrderMapper());

        $result = $client->fetchOrders(
            'https://example.test/api?ssid=abc&company=fms&product=sw&mandant=1&sys=live',
            'secret',
            0,
            'API-CUSTOM-READ'
        );

        static::assertSame('A-200', $result['orders'][0]['externalId'] ?? null);
    }


    public function testSendByFileTransferUrlUsesLowercaseTopmQueryParam(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->with(false)->willReturn('ok');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->callback(static function (string $url): bool {
                    $queryString = (string) parse_url($url, PHP_URL_QUERY);
                    parse_str($queryString, $params);

                    return str_contains($queryString, 'filetransferurl=')
                        && !str_contains($queryString, 'fileTransferUrl=')
                        && ($params['filetransferurl'] ?? null) === 'https://files.example.test/orders.xml';
                }),
                ['timeout' => 1.5]
            )
            ->willReturn($response);

        $logger = new TopmInMemoryLogger();
        $client = new TopmSan6Client($httpClient, $logger, new TopmSan6OrderMapper());

        $result = $client->sendByFileTransferUrl(
            'https://example.test/api?ssid=abc&company=fms&product=sw&mandant=1&sys=live',
            'secret',
            'https://files.example.test/orders.xml',
            1.5
        );

        static::assertSame('ok', $result);
    }

    public function testSendByPostXmlUsesCustomWriteFunctionWhenProvided(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->with(false)->willReturn('ok');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->callback(static function (string $url): bool {
                    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

                    return ($params['funktion'] ?? null) === 'API-CUSTOM-WRITE';
                }),
                [
                    'headers' => ['Content-Type' => 'application/xml'],
                    'body' => '<xml />',
                ]
            )
            ->willReturn($response);

        $logger = new TopmInMemoryLogger();
        $client = new TopmSan6Client($httpClient, $logger, new TopmSan6OrderMapper());

        $result = $client->sendByPostXml(
            'https://example.test/api?ssid=abc&company=fms&product=sw&mandant=1&sys=live',
            'secret',
            '<xml />',
            0,
            'API-CUSTOM-WRITE'
        );

        static::assertSame('ok', $result);
    }

    public function testSendByPostXmlUsesXmlContentType(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->with(false)->willReturn('ok');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->callback(static function (string $url): bool {
                    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

                    return ($params['funktion'] ?? null) === 'API-AUFTRAGNEU2';
                }),
                [
                    'timeout' => 3.0,
                    'headers' => ['Content-Type' => 'application/xml'],
                    'body' => '<xml />',
                ]
            )
            ->willReturn($response);

        $logger = new TopmInMemoryLogger();
        $client = new TopmSan6Client($httpClient, $logger, new TopmSan6OrderMapper());

        $result = $client->sendByPostXml('https://example.test/api?ssid=abc&company=fms&product=sw&mandant=1&sys=live', 'secret', '<xml />', 3.0);

        static::assertSame('ok', $result);
    }
}


final class TopmInMemoryLogger implements \Psr\Log\LoggerInterface
{
    /**
     * @var array<int, array{level: string, message: string, context: array<mixed>}>
     */
    private array $records = [];

    public function emergency($message, array $context = []): void { $this->log('emergency', (string) $message, $context); }
    public function alert($message, array $context = []): void { $this->log('alert', (string) $message, $context); }
    public function critical($message, array $context = []): void { $this->log('critical', (string) $message, $context); }
    public function error($message, array $context = []): void { $this->log('error', (string) $message, $context); }
    public function warning($message, array $context = []): void { $this->log('warning', (string) $message, $context); }
    public function notice($message, array $context = []): void { $this->log('notice', (string) $message, $context); }
    public function info($message, array $context = []): void { $this->log('info', (string) $message, $context); }
    public function debug($message, array $context = []): void { $this->log('debug', (string) $message, $context); }

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function hasRecord(string $level, string $message): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && $record['message'] === $message) {
                return true;
            }
        }

        return false;
    }
}
