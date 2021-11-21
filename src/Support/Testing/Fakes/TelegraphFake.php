<?php

/** @noinspection PhpUnused */

/** @noinspection PhpPropertyOnlyWrittenInspection */

namespace DefStudio\Telegraph\Support\Testing\Fakes;

use DefStudio\Telegraph\Telegraph;
use GuzzleHttp\Psr7\BufferStream;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

class TelegraphFake extends Telegraph
{
    /** @var array<int, mixed> */
    private array $sentMessages = [];

    /**
     * @param array<string, array<mixed>> $replies
     */
    public function __construct(private array $replies = [])
    {
        parent::__construct();
    }

    protected function sendRequestToTelegram(): Response
    {
        $this->sentMessages[] = [
            'url' => $this->getUrl(),
            'endpoint' => $this->endpoint ?? null,
            'data' => $this->data ?? null,
            'bot_token' => $this->botToken ?? null,
            'chat_id' => $this->chatId ?? null,
            'message' => $this->message ?? null,
            'keyboard' => $this->keyboard ?? null,
            'parse_mode' => $this->parseMode ?? null,
        ];


        $messageClass = new class () implements MessageInterface {
            /**
             * @param array<mixed> $reply
             */
            public function __construct(private array $reply = [])
            {
            }

            public function getProtocolVersion(): string
            {
                return "";
            }

            public function withProtocolVersion($version): static
            {
                return $this;
            }

            public function getHeaders(): array
            {
                return [];
            }

            public function hasHeader($name): bool
            {
                return false;
            }

            public function getHeader($name): array
            {
                return [];
            }

            public function getHeaderLine($name): string
            {
                return "";
            }

            public function withHeader($name, $value): static
            {
                return $this;
            }

            public function withAddedHeader($name, $value): static
            {
                return $this;
            }

            public function withoutHeader($name): static
            {
                return $this;
            }

            public function getBody(): StreamInterface
            {
                $buffer = new BufferStream();

                /** @phpstan-ignore-next-line  */
                $buffer->write(json_encode($this->reply));

                return $buffer;
            }

            public function withBody(StreamInterface $body): static
            {
                return $this;
            }
        };

        $response = $this->replies[$this->endpoint] ?? ['ok' => true];

        return new Response(new $messageClass($response));
    }

    /**
     * @param array<string, string> $data
     */
    public function assertSentData(string $endpoint, array $data = []): void
    {
        $foundMessages = collect($this->sentMessages)
            ->filter(fn (array $message): bool => $message['endpoint'] == $endpoint)
            ->filter(function (array $message) use ($data): bool {
                foreach ($data as $key => $value) {
                    if (!Arr::has($message['data'], $key)) {
                        return false;
                    }

                    if ($value != $message['data'][$key]) {
                        return false;
                    }
                }

                return true;
            });


        if ($data == null) {
            $errorMessage = sprintf("Failed to assert that a request was sent to [%s] endpoint (sent %d requests so far)", $endpoint, count($this->sentMessages));
        } else {
            $errorMessage = sprintf("Failed to assert that a request was sent to [%s] endpoint with the given data (sent %d requests so far)", $endpoint, count($this->sentMessages));
        }

        Assert::assertNotEmpty($foundMessages->toArray(), $errorMessage);
    }

    public function assertSent(string $message): void
    {
        $this->assertSentData(Telegraph::ENDPOINT_MESSAGE, [
            'text' => $message,
        ]);
    }

    public function assertRegisteredWebhook(): void
    {
        $this->assertSentData(Telegraph::ENDPOINT_SET_WEBHOOK);
    }

    public function assertRequestedWebhookDebugInfo(): void
    {
        $this->assertSentData(Telegraph::ENDPOINT_GET_WEBHOOK_DEBUG_INFO);
    }

    public function assertRepliedWebhook(string $message): void
    {
        $this->assertSentData(Telegraph::ENDPOINT_ANSWER_WEBHOOK, [
            'text' => $message,
        ]);
    }
}