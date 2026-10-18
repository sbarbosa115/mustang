<?php

namespace App\Tests\Unit\Messaging;

use App\Messaging\Domain\Model\MessageTemplate;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Error\InvalidValue;
use PHPUnit\Framework\TestCase;

final class MessageTemplateTest extends TestCase
{
    private function template(Channel $channel = Channel::Text, ?string $subject = null): MessageTemplate
    {
        return new MessageTemplate('d1', 'Name', $channel, 'Hi {first_name}', 'u1', $subject);
    }

    public function testRewriteChangesOnlyWhatIsGiven(): void
    {
        $t = $this->template();
        $t->rewrite(name: 'New', body: null, subject: null, active: false);
        self::assertSame('New', $t->name());
        self::assertSame('Hi {first_name}', $t->body());
        self::assertFalse($t->active());
    }

    public function testUnknownPlaceholdersAreRefusedWithTheFieldAndTheNames(): void
    {
        try {
            $this->template()->rewrite(body: 'Hi {x} {first_name} {y}');
            self::fail('Expected a refusal.');
        } catch (InvalidValue $e) {
            self::assertSame('unknown_placeholder', $e->errorCode());
            self::assertSame(['x', 'y'], $e->detail()['placeholders']);
            self::assertSame('body', $e->detail()['field']);
        }
    }

    public function testAnEmailWithoutASubjectIsRefusedAndATextDropsIt(): void
    {
        $this->expectException(InvalidValue::class);
        $this->template(Channel::Email)->rewrite(subject: '  ');
    }

    public function testATextTemplateNeverKeepsASubject(): void
    {
        $t = $this->template();
        $t->rewrite(subject: 'Ignored');
        self::assertNull($t->subject());
    }
}
