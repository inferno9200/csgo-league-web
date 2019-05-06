<?php

namespace Redline\League\Helpers;

use B3none\SteamIDConverter\Client as Converter;
use Redline\League\Models\PlayerModel;

class PlayersHelper extends BaseHelper
{
    const TABLE = 'sql_matches';

    /**
     * @var Converter
     */
    protected $converter;

    /**
     * @var ProfileHelper
     */
    protected $profileHelper;

    public function __construct()
    {
        parent::__construct();

        $this->profileHelper = new ProfileHelper();
        $this->converter = Converter::create();

        // This is a filthy hack to make sure that all of our players have a steam64 id
        $this->updatePlayers();
    }

    /**
     * Get the total number of players
     *
     * @return int
     */
    public function getPlayersCount(): int
    {
        return $this->db->count('rankme');
    }

    /**
     * Get players
     *
     * @param int $page
     * @return array
     */
    public function getPlayers(int $page): array
    {
        try {
            $limit = 12;
            $offset = ($page - 1) * $limit;

            $query = $this->db->query("SELECT * FROM rankme JOIN players ON players.steam = rankme.steam ORDER BY rankme.score DESC LIMIT :offset, :limit", [
                ':offset' => $offset,
                ':limit' => (int)$limit
            ]);

            $response = $query->fetchAll();

            foreach ($response as $key => $player) {
                $this->profileHelper->cacheProfileDetails($player['steamid64']);
                $response[$key] = $this->formatPlayer($player);
            }

            return $response;
        } catch (\Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');

            echo json_encode([
                'status' => 500
            ]);

            die;
        }
    }

    /**
     * Get top players
     *
     * @param int $players
     * @return array
     */
    public function getTopPlayers(int $players): array
    {
        try {
            $query = $this->db->query('SELECT * FROM rankme JOIN players ON players.steam = rankme.steam ORDER BY rankme.score DESC LIMIT :limit', [
                ':limit' => $players
            ]);

            $response = $query->fetchAll();

            foreach ($response as $key => $player) {
                $this->profileHelper->cacheProfileDetails($player['steamid64']);
                $response[$key] = $this->formatPlayer($player);
            }

            return $response;
        } catch (\Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');

            echo json_encode([
                'status' => 500
            ]);

            die;
        }
    }

    /**
     * Search players
     *
     * @param string $search
     * @return array
     */
    public function searchPlayers(string $search): array
    {
        try {
            $query = $this->db->query("SELECT * FROM rankme JOIN players ON players.steam = rankme.steam WHERE name LIKE :like_search OR steam = :search OR steamid64 = :search ORDER BY score DESC", [
                ':search' => $search,
                ':like_search' => '%'.$search.'%',
            ]);

            $response = $query->fetchAll();

            foreach ($response as $key => $player) {
                $response[$key] = $this->formatPlayer($player);
            }

            return $response;
        } catch (\Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');

            echo json_encode([
                'status' => 500
            ]);

            die;
        }
    }

    /**
     * @param array $player
     * @return array
     */
    public function formatPlayer(array $player): array
    {
        $playerModel = new PlayerModel($player);

        $player['kdr'] = $playerModel->getKDR();
        $player['adr'] = $playerModel->getADR();
        $player['accuracy'] = $playerModel->getAccuracy();

        return $player;
    }

    /**
     * Update players
     */
    public function updatePlayers(): void
    {
        try {
            $query = $this->db->query('SELECT rankme.steam FROM rankme WHERE rankme.steam IS NOT NULL AND rankme.steam NOT IN (SELECT steam FROM players)');

            $response = $query->fetchAll();

            foreach ($response as $player) {
                $steam = $this->converter->createFromSteamID($player['steam']);

                $this->db->insert('players', [
                    'steam' => $player['steam'],
                    'steamid64' => $steam->getSteamID64(),
                ]);
            }
        } catch (\Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');

            echo json_encode([
                'status' => 500
            ]);

            die;
        }
    }

    /**
     * Get player
     *
     * @param string $steamId
     * @return array
     */
    public function getPlayer(string $steamId): array
    {
        try {
            $query = $this->db->query("SELECT * FROM rankme JOIN players ON players.steam = rankme.steam WHERE players.steamid64 = :steam", [
                ':steam' => $steamId,
            ]);

            $player = $query->fetch();
            $this->profileHelper->cacheProfileDetails($player['steamid64']);

            return $this->formatPlayer($player);
        } catch (\Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');

            echo json_encode([
                'status' => 500
            ]);

            die;
        }
    }
}