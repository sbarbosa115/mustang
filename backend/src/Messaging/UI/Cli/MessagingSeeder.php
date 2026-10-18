<?php

namespace App\Messaging\UI\Cli;

use App\Messaging\Application\Port\MessageTransport;
use App\Messaging\Domain\Model\Conversation;
use App\Messaging\Domain\Model\Message;
use App\Messaging\Domain\Model\MessageDirection;
use App\Messaging\Domain\Model\MessageStatus;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Id;
use App\Shared\UI\Cli\DemoSeeder;
use App\Shared\UI\Cli\SeedContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A few threads so the inbox is not empty on day one, on customers 21-24 of Mustang Motors (the smoke suite and the
 * functional tests use the first ones): one fumbled, one waiting, one answered, one email.
 * Keys: conversation.<n> for customer n.
 */
final readonly class MessagingSeeder implements DemoSeeder
{
    public function __construct(private EntityManagerInterface $em, private MessageTransport $transport)
    {
    }

    public static function priority(): int
    {
        return 700;
    }

    public function seed(SeedContext $context): void
    {
        $sales = $context->id('user.sales@mustang.test');
        $sales2 = $context->id('user.sales2@mustang.test');
        $now = $context->now;
        $ago = static fn (string $interval): \DateTimeImmutable => $now->modify('-'.$interval);

        // Carlos Thompson asked two hours ago and nobody answered: fumbled.
        $this->thread($context, 21, $sales2, [
            ['in', Channel::Text, '+18135551021', 'Hi, is the 2024 Tacoma TRD still on the lot?', $ago('2 hours'), null],
        ]);
        // Ana White heard from Sam yesterday and replied ten minutes ago: waiting for an answer.
        $this->thread($context, 22, $sales, [
            ['out', Channel::Text, '+18135551022', 'Hi Ana, this is Sam from Mustang Motors. The CR-V you asked about just arrived.', $ago('1 day'), $sales],
            ['in', Channel::Text, '+18135551022', 'Great! Can I come by at 5 today?', $ago('10 minutes'), null],
        ]);
        // Luis Harris asked and Jordan answered.
        $this->thread($context, 23, $sales2, [
            ['in', Channel::Text, '+18135551023', 'Do you take trade-ins?', $ago('3 hours'), null],
            ['out', Channel::Text, '+18135551023', 'We do! Bring it by and we will appraise it in 20 minutes.', $ago('170 minutes'), $sales2],
        ]);
        // Sofia Sanchez prefers email.
        $this->thread($context, 24, $sales, [
            ['out', Channel::Email, 'sofia.sanchez@example.com', "Hi Sofia,\n\nThanks for visiting. The quote we discussed is attached to your file.\n\nSam", $ago('2 days'), $sales],
        ], 'Your quote from Mustang Motors');
    }

    /**
     * @param list<array{0: 'in'|'out', 1: Channel, 2: string, 3: string, 4: \DateTimeImmutable, 5: ?string}> $messages
     */
    private function thread(SeedContext $context, int $customer, string $assignee, array $messages, ?string $subject = null): void
    {
        $dealership = $context->id('dealership.mustang');
        $conversation = new Conversation($dealership, $context->id('customer.'.$customer), $assignee);
        $this->em->persist($conversation);

        foreach ($messages as [$direction, $channel, $contact, $body, $at, $sender]) {
            $dealer = $this->transport->senderFor($channel);
            if ('in' === $direction) {
                $message = new Message($dealership, $conversation->id(), MessageDirection::In, $channel, $dealer, $contact, $body, MessageStatus::Received, receivedAt: $at);
                $conversation->recordInbound($at);
            } else {
                $message = new Message(
                    $dealership,
                    $conversation->id(),
                    MessageDirection::Out,
                    $channel,
                    $contact,
                    $dealer,
                    $body,
                    MessageStatus::Sent,
                    subject: Channel::Email === $channel ? $subject : null,
                    sentByUserId: $sender,
                    consentStateSnapshot: ['state' => 'granted', 'channel' => $channel->value, 'contact_value' => $contact, 'checked_at' => $at->format(\DATE_ATOM), 'consent_id' => null, 'source' => 'demo_seed'],
                    transport: Channel::Email === $channel ? 'mailer' : 'fake_sms',
                    transportMessageId: Channel::Email === $channel ? Id::new().'@mustang.test' : 'fake-'.Id::new(),
                    sentAt: $at,
                );
                $conversation->recordOutbound($at);
            }
            $this->em->persist($message);
        }
        if ('out' === $messages[\count($messages) - 1][0]) {
            $conversation->markRead(); // The rep who answered has read the thread.
        }
        $context->set('conversation.'.$customer, $conversation->id());
    }
}
