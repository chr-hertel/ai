<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Pogocache;

use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\MessageStore\ConflictDetector;
use Symfony\AI\Agent\MessageStore\ManagedMessageStoreInterface;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Agent\Toolbox\Source\SourceCollectionNormalizer;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Serializer\MessageBagNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Keeps each conversation under its own key, next to a key listing the conversation ids for dropping them.
 *
 * New conversations are created only if absent, later saves check the stored messages first.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MessageStore implements ManagedMessageStoreInterface, MessageStoreInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $host,
        #[\SensitiveParameter] private readonly string $password,
        private readonly string $key = '_message_store_pogocache',
        private readonly SerializerInterface $serializer = new Serializer([
            new MessageBagNormalizer(),
            new SourceCollectionNormalizer(),
        ], [new JsonEncoder()]),
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('The Pogocache message store does not support any options.');
        }
    }

    public function drop(): void
    {
        foreach ($this->ids() as $id) {
            $this->delete($this->conversationKey($id));
        }

        $this->delete($this->key);
    }

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag
    {
        $response = $this->request('GET', $this->conversationKey($id->toRfc4122()));

        if (404 === $response->getStatusCode()) {
            return (new MessageBag())->withId($id);
        }

        return $this->serializer->deserialize($response->getContent(), MessageBag::class, 'json');
    }

    public function save(MessageBag $messages): void
    {
        $stored = $this->load($messages->getId());

        ConflictDetector::assertContinues($stored, $messages);

        $id = $messages->getId()->toRfc4122();
        $response = $this->request('PUT', $this->conversationKey($id), $this->serializer->serialize($messages, 'json'), [] === $stored->getMessages());

        if (404 === $response->getStatusCode()) {
            throw ConflictException::forMessages($messages);
        }

        $response->getContent();

        $ids = $this->ids();

        if (!\in_array($id, $ids, true)) {
            $this->request('PUT', $this->key, json_encode([...$ids, $id], \JSON_THROW_ON_ERROR))->getContent();
        }
    }

    /**
     * @return list<string>
     */
    private function ids(): array
    {
        $response = $this->request('GET', $this->key);

        if (404 === $response->getStatusCode()) {
            return [];
        }

        return json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function delete(string $key): void
    {
        $response = $this->request('DELETE', $key);

        if (404 !== $response->getStatusCode()) {
            $response->getContent();
        }
    }

    private function conversationKey(string $id): string
    {
        return $this->key.':'.$id;
    }

    private function request(string $method, string $key, ?string $body = null, bool $onlyIfAbsent = false): ResponseInterface
    {
        $query = ['auth' => $this->password];

        if ($onlyIfAbsent) {
            $query['nx'] = 1;
        }

        return $this->httpClient->request($method, \sprintf('%s/%s', $this->host, $key), [
            'query' => $query,
            'body' => $body ?? '',
        ]);
    }
}
