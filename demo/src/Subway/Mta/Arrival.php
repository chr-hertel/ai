<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Subway\Mta;

/**
 * One train on its way to the platform.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class Arrival
{
    public function __construct(
        public Line $line,
        public string $headsign,
        public string $directionLabel,
        public \DateTimeImmutable $arrivesAt,
    ) {
    }

    /**
     * @param array{line: string, headsign?: string|null, directionLabel?: string|null, arrivalTime: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Line::from($data['line']),
            $data['headsign'] ?? '',
            $data['directionLabel'] ?? '',
            new \DateTimeImmutable($data['arrivalTime']),
        );
    }

    /**
     * Recomputed on every render instead of trusting the "minutesAway" the feed shipped
     * with, so the board keeps ticking down between two fetches.
     */
    public function minutesAway(\DateTimeImmutable $now): int
    {
        return max(0, (int) floor(($this->arrivesAt->getTimestamp() - $now->getTimestamp()) / 60));
    }

    public function isArriving(\DateTimeImmutable $now): bool
    {
        return 0 === $this->minutesAway($now);
    }
}
