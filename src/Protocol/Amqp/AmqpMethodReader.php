<?php

declare(strict_types=1);

namespace Flux\Protocol\Amqp;

final class AmqpMethodReader
{
    private int $offset = 0;

    public function __construct(
        private readonly string $payload
    ) {
    }

    public function readOctet(): int
    {
        $this->requireBytes(1);

        return ord($this->payload[$this->offset++]);
    }

    public function readShort(): int
    {
        $this->requireBytes(2);
        $value = unpack('nvalue', substr($this->payload, $this->offset, 2));
        $this->offset += 2;

        return (int) $value['value'];
    }

    public function readShortString(): string
    {
        $length = $this->readOctet();
        $this->requireBytes($length);
        $value = substr($this->payload, $this->offset, $length);
        $this->offset += $length;

        return $value;
    }

    public function skipTable(): void
    {
        $this->requireBytes(4);
        $value = unpack('Nlength', substr($this->payload, $this->offset, 4));
        $this->offset += 4;
        $length = (int) $value['length'];
        $this->requireBytes($length);
        $this->offset += $length;
    }

    public function assertComplete(): void
    {
        if ($this->offset !== strlen($this->payload)) {
            throw new ProtocolException('AMQP method frame contains trailing bytes.');
        }
    }

    private function requireBytes(int $length): void
    {
        if ($length < 0 || strlen($this->payload) - $this->offset < $length) {
            throw new ProtocolException('AMQP method frame is malformed.');
        }
    }
}
