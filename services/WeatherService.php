<?php
declare(strict_types=1);

class WeatherService
{
    private PDO $db;
    private array $config;

    public function __construct()
    {
        $this->db = Database::connection();
        require_once __DIR__ . '/../config/config.php';
        $this->config = app_config();
    }

    /**
     * Fetch current weather for a farm's location from OpenWeatherMap and
     * persist it. Returns the stored row, or null on failure.
     */
    public function fetchAndStore(int $farmId, string $location): ?array
    {
        $apiKey = $this->config['weather']['api_key'];
        if ($apiKey === '') {
            Logger::warning('OPENWEATHER_API_KEY not configured; skipping weather fetch');
            return null;
        }

        $url = $this->config['weather']['base_url'] . '?' . http_build_query([
            'q' => $location,
            'appid' => $apiKey,
            'units' => 'metric',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            Logger::error("Weather fetch failed for farm $farmId: HTTP $httpCode $curlError");
            return null;
        }

        $json = json_decode($response, true);
        if (!is_array($json)) {
            return null;
        }

        $temperature = $json['main']['temp'] ?? null;
        $humidity = $json['main']['humidity'] ?? null;
        $rainfall = $json['rain']['1h'] ?? 0;
        // OpenWeatherMap's free "current weather" endpoint has no native
        // rain_probability field (that's in the One Call API); derive a
        // rough proxy from cloud cover when not otherwise available.
        $rainProbability = $json['pop'] ?? ($json['clouds']['all'] ?? null);

        $stmt = $this->db->prepare(
            'INSERT INTO weather_data (farm_id, temperature, humidity, rainfall, rain_probability) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$farmId, $temperature, $humidity, $rainfall, $rainProbability]);

        return [
            'farm_id' => $farmId,
            'temperature' => $temperature,
            'humidity' => $humidity,
            'rainfall' => $rainfall,
            'rain_probability' => $rainProbability,
        ];
    }

    public function latestForFarm(int $farmId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM weather_data WHERE farm_id = ? ORDER BY fetched_at DESC LIMIT 1'
        );
        $stmt->execute([$farmId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
