<?php

namespace App\Messaging\Domain\Model;

use App\Shared\Domain\Channel;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * A reusable text or email with placeholders (CRM-09).
 */
#[ORM\Entity]
#[ORM\Table(name: 'message_templates')]
#[ORM\Index(name: 'idx_message_templates_dealership', columns: ['dealership_id'])]
#[ORM\HasLifecycleCallbacks]
class MessageTemplate
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\Column(type: 'string', length: 120, name: 'name')]
    private string $name;
    #[ORM\Column(type: 'string', length: 20, enumType: Channel::class, name: 'channel')]
    private Channel $channel;
    #[ORM\Column(type: 'text', name: 'body')]
    private string $body;
    #[ORM\Column(type: 'guid', name: 'created_by_user_id')]
    private string $createdByUserId;
    #[ORM\Column(type: 'string', length: 255, name: 'subject', nullable: true)]
    private ?string $subject;
    #[ORM\Column(type: 'boolean', name: 'active')]
    private bool $active;

    public function __construct(
        string $dealershipId,
        string $name,
        Channel $channel,
        string $body,
        string $createdByUserId,
        ?string $subject = null,
        bool $active = true,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->name = $name;
        $this->channel = $channel;
        $this->body = $body;
        $this->createdByUserId = $createdByUserId;
        $this->subject = $subject;
        $this->active = $active;
    }

    /**
     * A new template that obeys the rules: text or email only, no unknown placeholder, an email has a subject and a
     * text has none.
     */
    public static function compose(string $dealershipId, string $name, Channel $channel, string $body, string $createdByUserId, ?string $subject = null, bool $active = true): self
    {
        $subject = self::checked($channel, $body, $subject);

        return new self($dealershipId, trim($name), $channel, $body, $createdByUserId, $subject, $active);
    }

    /**
     * Changes what is given (null leaves a field as it is). The channel never changes.
     */
    public function rewrite(?string $name = null, ?string $body = null, ?string $subject = null, ?bool $active = null): void
    {
        $newBody = $body ?? $this->body;
        $newSubject = $subject ?? $this->subject;
        $this->subject = self::checked($this->channel, $newBody, $newSubject);
        $this->body = $newBody;
        if (null !== $name) {
            $this->name = trim($name);
        }
        if (null !== $active) {
            $this->active = $active;
        }
    }

    private static function checked(Channel $channel, string $body, ?string $subject): ?string
    {
        if (!\in_array($channel, [Channel::Text, Channel::Email], true)) {
            throw new InvalidValue('template_channel_invalid', 'Templates are for texts and emails.', ['field' => 'channel']);
        }
        $subject = null === $subject ? null : trim($subject);
        $subject = '' === $subject ? null : $subject;
        if (Channel::Email === $channel && null === $subject) {
            throw new InvalidValue('subject_required', 'An email template needs a subject.', ['field' => 'subject']);
        }
        if (Channel::Text === $channel) {
            $subject = null;
        }
        foreach (['body' => $body, 'subject' => $subject ?? ''] as $field => $text) {
            $unknown = TemplatePlaceholders::unknownIn($text);
            if ([] !== $unknown) {
                throw new InvalidValue('unknown_placeholder', 'Unknown placeholder: {'.implode('}, {', $unknown).'}.', ['field' => $field, 'placeholders' => $unknown]);
            }
        }

        return $subject;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function dealershipId(): string
    {
        return $this->dealershipId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function channel(): Channel
    {
        return $this->channel;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function createdByUserId(): string
    {
        return $this->createdByUserId;
    }

    public function subject(): ?string
    {
        return $this->subject;
    }

    public function active(): bool
    {
        return $this->active;
    }
}
