<?php

namespace App\Messaging\Application\Query;

use App\Messaging\Domain\Model\Conversation;
use App\Messaging\Domain\Model\Message;

/**
 * Reads of the current dealership's inbox. "unreplied": the customer wrote last; "fumbled": unreplied for more
 * than WORKLIST_FUMBLED_MINUTES (30 by default).
 */
interface ConversationQueries
{
    public const array FOLDERS = ['all', 'unreplied', 'fumbled'];

    /**
     * Most recent message first.
     *
     * @param 'all'|'unreplied'|'fumbled' $folder
     *
     * @return array{items: list<Conversation>, total: int}
     */
    public function page(string $folder, ?string $userId, ?string $term, int $offset, int $limit): array;

    /**
     * @return array{all: int, unreplied: int, fumbled: int, by_user: array<string, int>} by_user: unreplied per rep
     */
    public function counts(): array;

    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's conversation
     */
    public function byId(string $id): Conversation;

    /**
     * The thread, oldest first, blocked attempts included (they are part of the record).
     *
     * @return list<Message>
     */
    public function messages(string $conversationId): array;

    /**
     * The last message of each conversation that left or reached the customer (blocked attempts left out).
     *
     * @param list<string> $conversationIds
     *
     * @return array<string, Message> conversation id => message
     */
    public function lastMessages(array $conversationIds): array;

    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's message
     */
    public function message(string $id): Message;

    public function fumbledAfterMinutes(): int;
}
