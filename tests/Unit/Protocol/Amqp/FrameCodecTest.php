<?php

declare(strict_types=1);

namespace Flux\Tests\Unit\Protocol\Amqp;

use Flux\Protocol\Amqp\Frame;
use Flux\Protocol\Amqp\FrameCodec;
use Flux\Protocol\Amqp\ProtocolException;
use PHPUnit\Framework\TestCase;

final class FrameCodecTest extends TestCase
{
    public function testItEncodesAndDecodesMethodFrames(): void
    {
        $codec = new FrameCodec();
        $encoded = $codec->encode(Frame::methodFrame(0, 10, 10, 'abc'));
        $frames = $codec->push($encoded);

        self::assertCount(1, $frames);
        self::assertSame(Frame::TYPE_METHOD, $frames[0]->type);
        self::assertSame(0, $frames[0]->channel);
        self::assertSame([10, 10], $frames[0]->method());
        self::assertSame('abc', substr($frames[0]->payload, 4));
    }

    public function testItBuffersPartialFrames(): void
    {
        $codec = new FrameCodec();
        $encoded = $codec->encode(Frame::methodFrame(0, 10, 10));

        self::assertSame([], $codec->push(substr($encoded, 0, 6)));
        self::assertSame(6, $codec->bufferedByteCount());

        $frames = $codec->push(substr($encoded, 6));

        self::assertCount(1, $frames);
        self::assertSame([10, 10], $frames[0]->method());
        self::assertSame(0, $codec->bufferedByteCount());
    }

    public function testItDecodesMultipleFramesFromOneBuffer(): void
    {
        $codec = new FrameCodec();
        $encoded = $codec->encode(Frame::methodFrame(0, 10, 11))
            . $codec->encode(Frame::methodFrame(0, 10, 31));

        $frames = $codec->push($encoded);

        self::assertCount(2, $frames);
        self::assertSame([10, 11], $frames[0]->method());
        self::assertSame([10, 31], $frames[1]->method());
    }

    public function testItRejectsInvalidFrameEndMarker(): void
    {
        $codec = new FrameCodec();
        $encoded = substr($codec->encode(Frame::methodFrame(0, 10, 10)), 0, -1) . "\x00";

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('AMQP frame end marker is invalid.');

        $codec->push($encoded);
    }

    public function testItRejectsOversizedFramesBeforeBufferingPayload(): void
    {
        $codec = new FrameCodec(maxFrameSize: 4);
        $encoded = pack('CnN', Frame::TYPE_METHOD, 0, 5);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('AMQP frame exceeds configured frame size limit.');

        $codec->push($encoded);
    }

    public function testItRejectsOversizedEncodedFrames(): void
    {
        $codec = new FrameCodec(maxFrameSize: 4);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('AMQP frame exceeds configured frame size limit.');

        $codec->encode(new Frame(Frame::TYPE_METHOD, 0, '12345'));
    }
}
