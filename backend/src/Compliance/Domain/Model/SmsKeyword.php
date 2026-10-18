<?php

namespace App\Compliance\Domain\Model;

/**
 * The carrier keywords a customer can text (FR-12): a whole message, any case, trailing punctuation ignored.
 */
enum SmsKeyword: string
{
    case Stop = 'stop';
    case Start = 'start';

    private const array STOP_WORDS = ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'];
    private const array START_WORDS = ['START', 'UNSTOP'];

    public static function fromMessage(string $body): ?self
    {
        $word = strtoupper(rtrim(trim($body), '.!'));

        return match (true) {
            \in_array($word, self::STOP_WORDS, true) => self::Stop,
            \in_array($word, self::START_WORDS, true) => self::Start,
            default => null,
        };
    }
}
