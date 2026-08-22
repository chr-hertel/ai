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

use App\Subway\Exception\SubwayException;
use Symfony\AI\McpBundle\Client\McpClientInterface;

/**
 * Reads live NYC transit data from the remote MCP server at subwayinfo.nyc.
 *
 * The server answers with both a human-readable Markdown rendering and a structured
 * payload; only the latter is used here, so the board can lay the data out itself.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class SubwayClient
{
    public function __construct(
        private McpClientInterface $nycClient,
    ) {
    }

    /**
     * @return list<Station>
     */
    public function searchStations(string $query, int $limit = 8): array
    {
        if ('' === trim($query)) {
            return [];
        }

        $data = $this->call('mta_search_stations', ['query' => $query, 'limit' => $limit * 4]);
        $stations = array_map(Station::fromArray(...), array_values($data['stations'] ?? []));

        return \array_slice($this->mergeComplexes($stations), 0, $limit);
    }

    /**
     * @return array{station: string, arrivals: list<Arrival>}
     */
    public function arrivals(string $stationId, int $limit = 12): array
    {
        $data = $this->call('mta_get_arrivals', ['station_id' => $stationId, 'limit' => $limit]);

        return [
            'station' => $data['stationName'] ?? $stationId,
            'arrivals' => array_map(Arrival::fromArray(...), array_values($data['arrivals'] ?? [])),
        ];
    }

    /**
     * The search returns one row per platform, so a complex like Times Sq shows up four
     * times. Asking for arrivals by name covers the whole complex, so the rows are merged
     * back into a single entry carrying every line served there.
     *
     * @param list<Station> $stations
     *
     * @return list<Station>
     */
    private function mergeComplexes(array $stations): array
    {
        $merged = [];

        foreach ($stations as $station) {
            $known = $merged[$station->name] ?? null;

            $merged[$station->name] = null === $known
                ? $station
                : new Station($known->id, $known->name, $known->borough, array_values(array_unique([...$known->lines, ...$station->lines])));
        }

        return array_values($merged);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function call(string $tool, array $arguments): array
    {
        try {
            $result = $this->nycClient->get('subway')->callTool($tool, $arguments);
        } catch (\Throwable $e) {
            throw SubwayException::callFailed($tool, $e);
        }

        $structured = $result->structuredContent;

        if (!\is_array($structured) || !\is_array($structured['data'] ?? null)) {
            throw SubwayException::unexpectedPayload($tool);
        }

        return $structured['data'];
    }
}
